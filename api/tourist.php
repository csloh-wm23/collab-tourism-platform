<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_once __DIR__.'/../config/auth.php';require_once __DIR__.'/../config/validation.php';
function tourist_reply(array $b,int $s=200):never{http_response_code($s);echo json_encode($b,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$user=current_user();if(!$user||($user['role']??'')!=='tourist'||($user['status']??'')!=='active')tourist_reply(['ok'=>false,'message'=>'Active tourist account required.'],403);
$db=database();$uid=(int)$user['id'];$method=$_SERVER['REQUEST_METHOD']??'GET';
try{
 if($method==='GET'){
  $stmt=$db->prepare('SELECT u.full_name,u.email,u.preferred_language,p.accessibility_notes,p.dietary_notes,p.allergy_notes,p.emergency_contact,p.emergency_details,p.default_destination,p.large_text,p.voice_playback FROM users u JOIN tourist_profiles p ON p.user_id=u.id WHERE u.id=?');$stmt->execute([$uid]);$profile=$stmt->fetch();
  $packs=$db->prepare('SELECT destination,scenario,language_code,added_at FROM user_saved_packs WHERE user_id=? ORDER BY added_at DESC');$packs->execute([$uid]);
  $scenario=(!empty($profile['dietary_notes'])||!empty($profile['allergy_notes']))?'restaurant':(!empty($profile['emergency_details'])?'medical':'transport');
  $recommend=$db->prepare('SELECT destination,scenario,source_text FROM phrase_packs WHERE destination IN (?,"Malaysia") AND scenario=? ORDER BY (destination=? ) DESC,id LIMIT 4');$recommend->execute([(string)($profile['default_destination']?:'Malaysia'),$scenario,(string)($profile['default_destination']?:'Malaysia')]);
  tourist_reply(['ok'=>true,'profile'=>$profile,'packs'=>$packs->fetchAll(),'recommendations'=>$recommend->fetchAll()]);
 }
 $input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input)||!verify_csrf($input['csrf']??null))tourist_reply(['ok'=>false,'message'=>'Invalid request token.'],403);
 if($method==='DELETE'){$db->beginTransaction();$db->prepare('DELETE FROM consent_records WHERE user_id=?')->execute([$uid]);$db->prepare('DELETE FROM records WHERE user_id=?')->execute([$uid]);$db->prepare('DELETE FROM user_destination_packs WHERE user_id=?')->execute([$uid]);$db->prepare('DELETE FROM user_saved_packs WHERE user_id=?')->execute([$uid]);$db->prepare('UPDATE translation_reports SET reporter_user_id=NULL WHERE reporter_user_id=?')->execute([$uid]);$db->prepare('UPDATE users SET preferred_language="en" WHERE id=?')->execute([$uid]);$db->prepare('UPDATE tourist_profiles SET accessibility_notes=NULL,dietary_notes=NULL,allergy_notes=NULL,emergency_contact=NULL,emergency_details=NULL,default_destination=NULL,large_text=0,voice_playback=0 WHERE user_id=?')->execute([$uid]);$db->commit();tourist_reply(['ok'=>true,'message'=>'Your profile preferences and saved journey data were deleted.']);}
 if($method!=='POST')tourist_reply(['ok'=>false,'message'=>'Method not allowed.'],405);
 $action=(string)($input['action']??'save_profile');
 if($action==='save_profile'){
  $language=clean_language($input['preferred_language']??'en');$fullName=clean_text($input['full_name']??'',120,true);
  $fields=[];foreach(['accessibility_notes','dietary_notes','allergy_notes','emergency_details'] as $field){$fields[$field]=clean_text($input[$field]??'',500);}$fields['emergency_contact']=clean_text($input['emergency_contact']??'',120);$fields['default_destination']=clean_text($input['default_destination']??'',120);
  $db->beginTransaction();$db->prepare('UPDATE users SET full_name=?,preferred_language=? WHERE id=?')->execute([$fullName,$language,$uid]);
  $db->prepare('UPDATE tourist_profiles SET accessibility_notes=?,dietary_notes=?,allergy_notes=?,emergency_contact=?,emergency_details=?,default_destination=?,large_text=?,voice_playback=? WHERE user_id=?')->execute([$fields['accessibility_notes'],$fields['dietary_notes'],$fields['allergy_notes'],$fields['emergency_contact'],$fields['emergency_details'],$fields['default_destination'],!empty($input['large_text'])?1:0,!empty($input['voice_playback'])?1:0,$uid]);$db->commit();tourist_reply(['ok'=>true,'message'=>'Tourist profile saved.']);
 }
 if($action==='add_pack'){$destination=clean_text($input['destination']??'',120,true);$scenario=clean_scenario($input['scenario']??'restaurant');$language=clean_language($input['language_code']??'ms');$db->prepare('INSERT IGNORE INTO user_saved_packs(user_id,destination,scenario,language_code) VALUES(?,?,?,?)')->execute([$uid,$destination,$scenario,$language]);tourist_reply(['ok'=>true]);}
 if($action==='remove_pack'){$destination=clean_text($input['destination']??'',120,true);$scenario=clean_scenario($input['scenario']??'restaurant');$language=clean_language($input['language_code']??'ms');$db->prepare('DELETE FROM user_saved_packs WHERE user_id=? AND destination=? AND scenario=? AND language_code=?')->execute([$uid,$destination,$scenario,$language]);tourist_reply(['ok'=>true]);}
 tourist_reply(['ok'=>false,'message'=>'Unknown action.'],422);
}catch(InvalidArgumentException $e){if($db->inTransaction())$db->rollBack();tourist_reply(['ok'=>false,'message'=>$e->getMessage()],422);}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Tourist: '.$e->getMessage());tourist_reply(['ok'=>false,'message'=>'Could not update journey settings.'],500);}
