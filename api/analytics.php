<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_once __DIR__.'/../config/auth.php';require_once __DIR__.'/../config/validation.php';
function analytics_reply(array $b,int $s=200):never{http_response_code($s);echo json_encode($b,JSON_UNESCAPED_UNICODE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')analytics_reply(['ok'=>false,'message'=>'Method not allowed.'],405);
$input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input)||!verify_csrf($input['csrf']??null))analytics_reply(['ok'=>false,'message'=>'Invalid request.'],403);
if(($input['consent']??false)!==true)analytics_reply(['ok'=>true,'recorded'=>false]);
try{
 $event=clean_text($input['event_type']??'',80,true);$language=isset($input['language_code'])?clean_language($input['language_code']):null;
 $scenario=isset($input['scenario'])?clean_scenario($input['scenario']):null;$location=clean_text($input['location_label']??'',120);$businessType=clean_text($input['business_type']??'',80);$term=clean_text($input['term_label']??'',120);$confidence=clean_confidence($input['confidence']??null);
 database()->prepare('INSERT INTO analytics_events(event_type,language_code,scenario,location_label,business_type,term_label,confidence) VALUES(?,?,?,?,?,?,?)')->execute([$event,$language,$scenario,$location?:null,$businessType?:null,$term?:null,$confidence]);
 analytics_reply(['ok'=>true,'recorded'=>true]);
}catch(InvalidArgumentException $e){analytics_reply(['ok'=>false,'message'=>$e->getMessage()],422);}catch(Throwable $e){error_log('Analytics: '.$e->getMessage());analytics_reply(['ok'=>false,'message'=>'Could not record anonymous analytics.'],500);}
