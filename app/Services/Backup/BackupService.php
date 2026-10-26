<?php

namespace App\Services\Backup;

use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Daily ZIP of the whole database (as SQL) plus member photos, kept on the
 * private disk. Written in PHP so it works on WAMP without mysqldump.
 */
class BackupService
{
    public const DIRECTORY = 'backups';

    /** Structure is kept, rows are not: these tables only hold throwaway state. */
    private const SKIP_DATA = ['sessions', 'cache', 'cache_locks'];

    public function create(): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);

        $name = self::DIRECTORY.'/mjeshi-'.now()->format('Y-m-d-His').'.zip';
        $sql = tempnam(sys_get_temp_dir(), 'mjeshi-sql');

        try {
            $this->dumpDatabase($sql);

            $zip = new ZipArchive;
            if ($zip->open($disk->path($name), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Cannot create {$name}.");
            }

            $zip->addFile($sql, 'database.sql');
            $zip->addFromString('README.txt', $this->readme());

            foreach ($disk->allFiles('member-photos') as $photo) {
                $zip->addFile($disk->path($photo), $photo);
            }

            $zip->close();
        } finally {
            @unlink($sql);
        }

        return $name;
    }

    /** @return Collection<int, array{name: string, size: int, created_at: CarbonImmutable}> newest first */
    public function list(): Collection
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIRECTORY))
            ->filter(fn ($path) => str_ends_with($path, '.zip'))
            ->map(fn ($path) => [
                'name' => basename($path),
                'size' => $disk->size($path),
                'created_at' => CarbonImmutable::createFromTimestamp($disk->lastModified($path))->setTimezone(config('app.timezone')),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    /** Remove backups older than the kept number of days, always keeping the newest. */
    public function prune(): int
    {
        $days = max(1, (int) Settings::get('backup_keep_days'));
        $old = $this->list()->skip(1)->filter(fn ($b) => $b['created_at']->lt(now()->subDays($days)));

        foreach ($old as $backup) {
            Storage::disk('local')->delete(self::DIRECTORY.'/'.$backup['name']);
        }

        return $old->count();
    }

    public function path(string $name): ?string
    {
        // Only plain file names we created; never a path from the request.
        if (! preg_match('/^mjeshi-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/', $name) || ! Storage::disk('local')->exists(self::DIRECTORY.'/'.$name)) {
            return null;
        }

        return Storage::disk('local')->path(self::DIRECTORY.'/'.$name);
    }

    private function dumpDatabase(string $file): void
    {
        $out = fopen($file, 'w');
        $driver = DB::getDriverName();
        $mysql = in_array($driver, ['mysql', 'mariadb'], true);
        $q = fn (string $name) => $mysql ? "`{$name}`" : "\"{$name}\"";

        fwrite($out, '-- '.config('app.name').' backup '.now()->toDateTimeString()." ({$driver})\n");
        fwrite($out, $mysql ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" : "PRAGMA foreign_keys=OFF;\n\n");

        foreach ($this->tables($mysql) as $table => $create) {
            fwrite($out, "DROP TABLE IF EXISTS {$q($table)};\n{$create};\n\n");

            if (in_array($table, self::SKIP_DATA, true)) {
                continue;
            }

            $batch = [];
            foreach (DB::table($table)->cursor() as $row) {
                $batch[] = '('.implode(', ', array_map(fn ($v) => $this->value($v), (array) $row)).')';

                if (count($batch) === 200) {
                    $this->writeInsert($out, $q($table), $batch);
                    $batch = [];
                }
            }
            $this->writeInsert($out, $q($table), $batch);
        }

        fwrite($out, $mysql ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n");
        fclose($out);
    }

    /** @return array<string, string> table => CREATE statement */
    private function tables(bool $mysql): array
    {
        if ($mysql) {
            return collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
                ->map(fn ($row) => array_values((array) $row)[0])
                ->mapWithKeys(fn ($t) => [$t => ((array) DB::selectOne("SHOW CREATE TABLE `{$t}`"))['Create Table']])
                ->all();
        }

        return collect(DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
            ->mapWithKeys(fn ($row) => [$row->name => $row->sql])
            ->all();
    }

    private function writeInsert($out, string $table, array $rows): void
    {
        if ($rows) {
            fwrite($out, "INSERT INTO {$table} VALUES\n".implode(",\n", $rows).";\n\n");
        }
    }

    private function value(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_int($value), is_float($value) => (string) $value,
            default => DB::getPdo()->quote((string) $value),
        };
    }

    private function readme(): string
    {
        // Both languages: whoever restores may not be whoever made the backup.
        return Settings::systemName().' - '.now()->format('d/m/Y H:i')."\n\n"
            ."To restore:\n"
            ."1. Create an empty database (or use the existing one; tables are replaced).\n"
            ."2. Import database.sql with phpMyAdmin (Import) or: mysql -u root mjeshi < database.sql\n"
            ."3. Copy the member-photos folder to storage/app/private/member-photos\n\n"
            ."Kurejesha:\n"
            ."1. Unda database tupu (au tumia iliyopo; majedwali yataandikwa upya).\n"
            ."2. Ingiza database.sql kwa phpMyAdmin (Import) au: mysql -u root mjeshi < database.sql\n"
            ."3. Nakili folda member-photos kwenda storage/app/private/member-photos\n";
    }
}
