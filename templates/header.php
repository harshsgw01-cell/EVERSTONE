<?php
if (!function_exists('app_base_path')) {
    function app_base_path()
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        foreach (['/public/', '/modules/'] as $segment) {
            $pos = strpos($script, $segment);
            if ($pos !== false) {
                return rtrim(substr($script, 0, $pos), '/');
            }
        }

        return '';
    }
}

if (!function_exists('app_url')) {
    function app_url($path = '')
    {
        $base = app_base_path();
        $path = '/' . ltrim($path, '/');

        return ($base === '' ? '' : $base) . $path;
    }
}

if (!function_exists('asset_url')) {
    function asset_url($path = '')
    {
        return app_url('assets/' . ltrim($path, '/'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVERSTONE CRM</title>
    <link rel="shortcut icon" href="https://everstonetech.ca/assets/Everstone.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/tcs-unified.css')) ?>">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
      <!-- <style>
        /* Page-level overrides only; colours come from tcs-theme.css */

        body {
            min-height: 100vh;
            background: var(--color-bg);
            color: var(--color-text-primary);
        }

        
    </style>
     -->
</head>


<body class="<?= htmlspecialchars($body_class ?? '') ?>">
