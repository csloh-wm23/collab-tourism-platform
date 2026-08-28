<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
require_once __DIR__.'/../config/database.php'; require_once __DIR__.'/../config/validation.php';
try {
    $scenario=clean_scenario($_GET['scenario']??'restaurant');
    $destination=clean_text($_GET['destination']??'Malaysia',120,true);
    $stmt=database()->prepare('SELECT id,destination,scenario,language_code,source_text,translated_text,suggested_reply,cultural_tip,is_offline_ready FROM phrase_packs WHERE destination=? AND scenario=? ORDER BY id');
    $stmt->execute([$destination,$scenario]);
    echo json_encode(['ok'=>true,'phrases'=>$stmt->fetchAll()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Assistance: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Assistance phrases are unavailable.']);}
