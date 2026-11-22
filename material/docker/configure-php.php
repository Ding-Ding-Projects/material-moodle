<?php
// Generate the PHP limits from the same finite choices offered in the deployer.
// Run before Moodle starts. No input is interpreted as PHP or shell syntax.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$settings = json_decode(file_get_contents(__DIR__ . '/php-settings.json'), true, flags: JSON_THROW_ON_ERROR);
$values = [];
foreach ($settings as $name => $spec) {
    $key = 'MATERIAL_PHP_' . strtoupper(str_replace('.', '_', $name));
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $spec['default'];
    }
    if (!in_array($value, $spec['options'], true)) {
        fwrite(STDERR, "Unsupported setting for $name. Choose one of the deployment form values.\n");
        exit(1);
    }
    $values[$name] = $value;
}
if ((int)$values['upload_max_filesize'] > (int)$values['post_max_size']) {
    fwrite(STDERR, "The request body limit must be at least the upload limit.\n");
    exit(1);
}
foreach ($values as $name => $value) {
    echo "$name = $value\n";
}
