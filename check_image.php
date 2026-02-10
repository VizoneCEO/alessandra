<?php
$path = 'front/multimedia/boleto.jpeg';
if (file_exists($path)) {
    $size = getimagesize($path);
    echo "Width: " . $size[0] . "\n";
    echo "Height: " . $size[1] . "\n";
    echo "MIME: " . $size['mime'] . "\n";
} else {
    echo "File not found at $path";
}
?>