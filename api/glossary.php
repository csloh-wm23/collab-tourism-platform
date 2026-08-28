<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
require_once __DIR__.'/../config/database.php';
try{$rows=database()->query('SELECT term,explanation,category FROM malaysian_terms ORDER BY term')->fetchAll();echo json_encode(['ok'=>true,'terms'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
catch(Throwable $e){error_log('Glossary: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Terminology is unavailable.']);}
