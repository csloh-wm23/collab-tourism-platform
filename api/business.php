<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_once __DIR__.'/../config/auth.php';require_once __DIR__.'/../config/validation.php';
function business_reply(array $b,int $s=200):never{http_response_code($s);echo json_encode($b,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 $user=current_user();if(!$user||($user['role']??'')!=='business')business_reply(['ok'=>false,'message'=>'Business account access required.'],403);
 if(($user['status']??'')!=='active')business_reply(['ok'=>false,'message'=>'Approved active business account required.'],403);
 $db=database();$stmt=$db->prepare('SELECT * FROM businesses WHERE owner_user_id=? LIMIT 1');$stmt->execute([(int)$user['id']]);$business=$stmt->fetch();
 if(!$business||($business['verification_status']??'')!=='approved')business_reply(['ok'=>false,'message'=>'Pending administrator approval.'],403);
 $bid=(int)$business['id'];$method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='GET'){
  $p=$db->prepare('SELECT id,source_language,target_language,source_text,translated_text,suggested_reply,category,is_published FROM business_phrases WHERE business_id=? ORDER BY created_at DESC');$p->execute([$bid]);
  $f=$db->prepare('SELECT id,question,answer,is_published FROM business_faqs WHERE business_id=? ORDER BY created_at DESC');$f->execute([$bid]);
  $t=$db->prepare('SELECT id,term,explanation FROM business_terms WHERE business_id=? ORDER BY term');$t->execute([$bid]);
  $reports=$db->prepare('SELECT category,language_code,COUNT(*) total FROM business_interactions WHERE business_id=? GROUP BY category,language_code ORDER BY total DESC');$reports->execute([$bid]);
  business_reply(['ok'=>true,'business'=>$business,'phrases'=>$p->fetchAll(),'faqs'=>$f->fetchAll(),'terms'=>$t->fetchAll(),'reports'=>$reports->fetchAll(),'public_url'=>'business.php?slug='.rawurlencode((string)$business['qr_slug'])]);
 }
 if($method!=='POST')business_reply(['ok'=>false,'message'=>'Method not allowed.'],405);
 $input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input)||!verify_csrf($input['csrf']??null))business_reply(['ok'=>false,'message'=>'Invalid request token.'],403);$action=(string)($input['action']??'');
 if($action==='save_profile'){
  $name=clean_text($input['name']??'',160,true);$category=clean_text($input['category']??'',80,true);$address=clean_text($input['address']??'',500,true);
  $description=clean_text($input['description']??'',3000);$services=clean_text($input['service_details']??'',3000);$payments=clean_text($input['payment_methods']??'',500);$menu=clean_text($input['menu_details']??'',5000);$facilities=clean_text($input['facility_details']??'',3000);
  $db->prepare('UPDATE businesses SET name=?,category=?,address=?,description=?,service_details=?,payment_methods=?,menu_details=?,facility_details=?,is_public=? WHERE id=?')->execute([$name,$category,$address,$description,$services,$payments,$menu,$facilities,!empty($input['is_public'])?1:0,$bid]);business_reply(['ok'=>true]);
 }
 if($action==='add_phrase'){
  $source=clean_text($input['source_text']??'',500,true);$translated=clean_text($input['translated_text']??'',500,true);$reply=clean_text($input['suggested_reply']??'',500);$target=clean_language($input['target_language']??'ms');$category=clean_text($input['category']??'General',80,true);
  $db->prepare('INSERT INTO business_phrases(business_id,source_language,target_language,source_text,translated_text,suggested_reply,category) VALUES(?,?,?,?,?,?,?)')->execute([$bid,'en',$target,$source,$translated,$reply,$category]);business_reply(['ok'=>true,'id'=>(int)$db->lastInsertId()],201);
 }
 if($action==='add_faq'){$q=clean_text($input['question']??'',500,true);$a=clean_text($input['answer']??'',1000,true);$db->prepare('INSERT INTO business_faqs(business_id,question,answer) VALUES(?,?,?)')->execute([$bid,$q,$a]);business_reply(['ok'=>true],201);}
 if($action==='add_term'){$term=clean_text($input['term']??'',120,true);$explanation=clean_text($input['explanation']??'',500,true);$db->prepare('INSERT INTO business_terms(business_id,term,explanation) VALUES(?,?,?) ON DUPLICATE KEY UPDATE explanation=VALUES(explanation)')->execute([$bid,$term,$explanation]);business_reply(['ok'=>true],201);}
 if($action==='delete_item'){$type=(string)($input['item_type']??'');$id=(int)($input['id']??0);$tables=['phrase'=>'business_phrases','faq'=>'business_faqs','term'=>'business_terms'];if(!isset($tables[$type])||$id<1)business_reply(['ok'=>false,'message'=>'Invalid item.'],422);$db->prepare("DELETE FROM {$tables[$type]} WHERE id=? AND business_id=?")->execute([$id,$bid]);business_reply(['ok'=>true]);}
 business_reply(['ok'=>false,'message'=>'Unknown action.'],422);
}catch(InvalidArgumentException $e){business_reply(['ok'=>false,'message'=>$e->getMessage()],422);}catch(Throwable $e){error_log('Business: '.$e->getMessage());business_reply(['ok'=>false,'message'=>'Business data is unavailable.'],500);}
