<?php
declare(strict_types=1);

$root=dirname(__DIR__);$failures=[];$checks=0;
function check(bool $condition,string $message):void{global $failures,$checks;$checks++;if(!$condition)$failures[]=$message;}
require_once $root.'/config/validation.php';
require_once $root.'/config/security.php';

check(array_keys(supported_languages())===['en','ms','zh','id','th'],'Supported languages must exactly match the proposal.');
check(array_key_exists('auto',supported_languages(true)),'Automatic language detection must be supported.');
check(supported_scenarios()===['restaurant','hotel','transport','shopping','medical','emergency','culture'],'All proposal scenarios must be supported.');
check(clean_language('id')==='id'&&clean_language('th')==='th','Indonesian and Thai validation failed.');
try{clean_language('ta');check(false,'Tamil must not be accepted.');}catch(InvalidArgumentException $e){check(true,'Tamil is rejected.');}
check(clean_confidence('0.72')===0.72,'Confidence parsing failed.');
try{clean_text(str_repeat('a',501),500);check(false,'Oversized text must fail.');}catch(InvalidArgumentException $e){check(true,'Oversized text rejected.');}
check(failed_login_state(3)===['attempts'=>4,'lock_minutes'=>0,'locked'=>false],'Fourth failed login must not lock early.');
check(failed_login_state(4)===['attempts'=>0,'lock_minutes'=>15,'locked'=>true],'Fifth failed login must trigger the 15-minute lock.');

$requiredFiles=['index.php','business.php','config/security.php','assets/js/core.js','api/translate.php','api/speech.php','api/assistance.php','api/glossary.php','api/report.php','api/analytics.php','api/tourist.php','api/profile.php','api/business.php','api/public_business.php','api/insights.php','database/jomcommunicate.sql','database/migrations/20260828_document_features.sql'];
foreach($requiredFiles as $file)check(is_file($root.'/'.$file),'Missing required file: '.$file);
$all='';foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){$path=$file->getPathname();if($file->isFile()&&!str_contains($path,DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR)&&!str_contains($path,DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR))$all.=file_get_contents($path)."\n";}
check(!preg_match('/Tamil|ta-IN|ta-MY|value=["\']ta["\']/i',$all),'Tamil remains in application files.');
foreach(['Automatic detection','Two-way conversation','Report unclear','Indonesian','Thai','Medical','Emergency','Destination packs','Frequently asked questions','Export CSV'] as $needle)check(str_contains($all,$needle),'Missing proposal feature marker: '.$needle);
check(str_contains($all,'dark-mode')&&str_contains($all,'jomcommunicate_theme'),'Persistent dark mode is missing.');
check(str_contains($all,'topbar-title'),'Header title/date spacing class is missing.');
check(str_contains($all,'sidebarBackdrop')&&str_contains($all,'closeMenuButton'),'Dismissible navigation controls are missing.');
check(str_contains($all,"event.key==='Escape'")&&str_contains($all,"setMenu(false)"),'Keyboard and programmatic navigation dismissal are missing.');
check(str_contains($all,"addEventListener('hashchange'")&&str_contains($all,"history.pushState"),'Hash and browser-history navigation handling is missing.');
check(!str_contains($all,"'confidence'=>0.92")&&!str_contains($all,'Number(data.confidence||0)'),'A fabricated translation confidence remains.');
check(str_contains($all,'not_provided_by_google')&&str_contains($all,'confidenceLabel'),'Truthful translation-confidence handling is missing.');
check(str_contains($all,'twoWayLanguages')&&str_contains($all,"source==='auto'?detected:source"),'Automatic-source two-way switching is not covered.');
check(str_contains($all,"Choose the spoken language before using the microphone"),'Automatic voice input must not silently fall back to English.');
check(str_contains($all,'guest_preferences')&&str_contains($all,"consent_type='anonymous_analytics'"),'Server-side analytics consent verification is missing.');
check(str_contains($all,'source_hash')&&str_contains($all,'VALUES(NULL,NULL,NULL'),'Anonymous issue reporting is incomplete.');
check(str_contains($all,'business_profile_translations')&&str_contains($all,'toggle_item')&&str_contains($all,'update_item'),'Multilingual editable business content is incomplete.');
check(str_contains($all,'resubmit_application'),'Rejected business application resubmission is missing.');
$index=file_get_contents($root.'/index.php');
check(substr_count($index,'id="mainNavigation"')===1&&str_contains($index,'</aside>'),'Navigation aside markup is unbalanced.');
check(strpos($index,'</aside>')<strpos($index,'id="sidebarBackdrop"'),'The page must not be nested inside the hidden navigation drawer.');
$css=file_get_contents($root.'/assets/css/styles.css');
check(str_contains($css,'.profile-layout')&&str_contains($css,'.module-tabs'),'TravEase profile and modular workspace styling is missing.');
check(str_contains($index,'TravEase')&&str_contains($index,'Profile & settings'),'TravEase branding or profile management is missing.');
$login=file_get_contents($root.'/login.php');check(str_contains($login,'failed_login_attempts')&&str_contains($login,'INTERVAL 15 MINUTE'),'Login lockout is missing.');
$profile=file_get_contents($root.'/api/profile.php');check(str_contains($profile,'update_account')&&str_contains($profile,'change_password')&&str_contains($profile,'password_verify'),'Account profile or secure password management is missing.');
$schema=file_get_contents($root.'/database/jomcommunicate.sql');foreach(['business_profile_translations','business_faqs','business_terms','malaysian_terms','phrase_packs','user_saved_packs','analytics_events','business_interactions'] as $table)check(str_contains($schema,'CREATE TABLE IF NOT EXISTS '.$table),'Schema table missing: '.$table);
foreach(['restaurant','hotel','transport','shopping','medical','emergency'] as $scenario)check(substr_count($schema,"'Malaysia','{$scenario}','ms'")>=3,"Assistant workflow seed is incomplete for {$scenario}.");

if($failures){fwrite(STDERR,"FAILED ({$checks} checks)\n- ".implode("\n- ",$failures)."\n");exit(1);}echo "PASS: {$checks} application checks\n";
