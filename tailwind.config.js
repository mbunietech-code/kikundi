/** Build: npx tailwindcss@3.4.17 -i resources/css/mjeshi.css -o public/css/app.css --minify */
module.exports = {
    content: ['./resources/views/**/*.blade.php', './app/**/*.php'],
    theme: {
        extend: {
            fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'sans-serif'] },
        },
    },
};
