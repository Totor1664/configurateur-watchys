<?php
/* Helpers de conversion ; aucun accès à la base ou au site. */
function watchysJpegBytes($source, $width = 0, $height = 0) {
    $image = imagecreatefrompng($source);
    if (!$image) { throw new RuntimeException('Image source illisible.'); }
    $sw = imagesx($image); $sh = imagesy($image);
    if ($width === 0 && $height === 0) { $width = $sw; $height = $sh; }
    if ($width < 1 || $height < 1 || $width > 4000 || $height > 4000) { throw new RuntimeException('Dimensions JPEG invalides.'); }
    $canvas = imagecreatetruecolor($width, $height);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    $scale = min($width / $sw, $height / $sh);
    $w = max(1, (int)round($sw * $scale)); $h = max(1, (int)round($sh * $scale));
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $image, (int)(($width-$w)/2), (int)(($height-$h)/2), 0, 0, $w, $h, $sw, $sh);
    ob_start();
    try { if (!imagejpeg($canvas, null, 95)) { throw new RuntimeException('Conversion JPEG échouée.'); } $bytes = ob_get_contents(); }
    finally { ob_end_clean(); imagedestroy($canvas); imagedestroy($image); }
    $size = getimagesizefromstring($bytes);
    if (!$size || $size[0] !== $width || $size[1] !== $height || $size[2] !== IMAGETYPE_JPEG) { throw new RuntimeException('JPEG généré non conforme.'); }
    return $bytes;
}
function watchysWriteExclusive($path, $bytes) {
    $out = fopen($path, 'xb');
    if (!$out) { throw new RuntimeException('Fichier préexistant ou création impossible.'); }
    try {
        $offset=0; $length=strlen($bytes);
        while ($offset < $length) { $n=fwrite($out, substr($bytes, $offset)); if (!$n) { throw new RuntimeException('Écriture JPEG incomplète.'); } $offset+=$n; }
        if (!fflush($out)) { throw new RuntimeException('JPEG non synchronisé.'); }
    } finally { fclose($out); }
    if (!chmod($path, 0644) || hash_file('sha256', $path) !== hash('sha256', $bytes)) { throw new RuntimeException('Relecture JPEG échouée.'); }
}
