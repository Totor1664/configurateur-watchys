<?php
require __DIR__.'/images.php';
foreach (['images.php','installer.php'] as $file) { token_get_all(file_get_contents(__DIR__.'/'.$file),TOKEN_PARSE); }
$source=__DIR__.'/montre-generique.png';
foreach ([[0,0,1254,1255],[98,98,98,98],[250,250,250,250],[452,584,452,584],[120,80,120,80]] as [$w,$h,$ew,$eh]) {
    $bytes=watchysJpegBytes($source,$w,$h); $size=getimagesizefromstring($bytes);
    if ($size[0]!==$ew || $size[1]!==$eh || $size[2]!==IMAGETYPE_JPEG) { throw new Exception('Dimensions non conformes.'); }
    $image=imagecreatefromstring($bytes); $rgb=imagecolorat($image,0,0);
    if ((($rgb>>16)&255)<250 || (($rgb>>8)&255)<250 || ($rgb&255)<250) { throw new Exception('Fond non blanc.'); }
    imagedestroy($image);
}
$path=__DIR__.'/vignette-test-'.bin2hex(random_bytes(3)).'.jpg'; $bytes=watchysJpegBytes($source,98,98);
watchysWriteExclusive($path,$bytes); $before=hash_file('sha256',$path); $rejected=false;
set_error_handler(static function(){return true;});
try { watchysWriteExclusive($path,'different'); } catch (RuntimeException $e) { $rejected=true; }
restore_error_handler();
if (!$rejected || hash_file('sha256',$path)!==$before) { throw new Exception('Fichier préexistant modifié.'); }
unlink($path);
$rejected=false; try { watchysJpegBytes($source,0,100); } catch (RuntimeException $e) { $rejected=true; }
if (!$rejected) { throw new Exception('Dimensions invalides acceptées.'); }
echo "SUCCÈS — Syntaxe PHP 8.2, cinq formats JPEG, fond blanc, refus des dimensions invalides et des fichiers préexistants. Aucun accès MySQL.\n";
