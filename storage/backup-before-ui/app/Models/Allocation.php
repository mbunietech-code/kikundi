<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group_id', 'payment_id', 'target_type', 'target_id', 'credited_member_id', 'amount', 'currency', 'reversal_of_id'])]
class Allocation extends Model {}
