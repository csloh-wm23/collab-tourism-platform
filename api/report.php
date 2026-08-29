<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_once __DIR__.'/../config/auth.php';require_once __DIR__.'/../config/validation.php';
function report_reply(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')report_reply(['ok'=>false,'message'=>'Method not allowed.'],405);
$input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))report_reply(['ok'=>false,'message'=>'Invalid JSON body.'],400);
if(!verify_csrf($input['csrf']??null))report_reply(['ok'=>false,'message'=>'Invalid request token.'],403);
try{
 $source=clean_text($input['source_text']??'',1000,true);$translated=clean_text($input['translated_text']??'',1000,true);
 $from=clean_language($input['source_language']??'',true);$to=clean_language($input['target_language']??'');$scenario=clean_scenario($input['scenario']??'culture');$confidence=clean_confidence($input['confidence']??null);
 $issue=clean_text($input['issue_type']??'unclear_translation',80,true);$notes=clean_text($input['notes']??'',1000);$term=clean_text($input['term_label']??'',120);
 // Reports intentionally keep only analytical dimensions and a one-way message
 // fingerprint. They do not retain the reporter identity or conversation text.
 $stmt=database()->prepare('INSERT INTO translation_reports(reporter_user_id,source_text,translated_text,source_hash,source_language,target_language,scenario,confidence,issue_type,term_label,notes) VALUES(NULL,NULL,NULL,?,?,?,?,?,?,?,?)');
 $stmt->execute([hash('sha256',mb_strtolower($source)),$from,$to,$scenario,$confidence,$issue,$term?:null,$notes?:null]);
 report_reply(['ok'=>true,'message'=>'Thank you. The unclear translation was reported.']);
}catch(InvalidArgumentException $e){report_reply(['ok'=>false,'message'=>$e->getMessage()],422);}catch(Throwable $e){error_log('Report: '.$e->getMessage());report_reply(['ok'=>false,'message'=>'Could not submit the report.'],500);}
