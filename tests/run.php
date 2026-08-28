<?php
declare(strict_types=1);

$root=dirname(__DIR__);$failures=[];$checks=0;
function check(bool $condition,string $message):void{global $failures,$checks;$checks++;if(!$condition)$failures[]=$message;}
require_once $root.'/config/validation.php';

check(array_keys(supported_languages())===['en','ms','zh','id','th'],'Supported languages must exactly match the proposal.');
check(array_key_exists('auto',supported_languages(true)),'Automatic language detection must be supported.');
check(supported_scenarios()===['restaurant','hotel','transport','shopping','medical','emergency','culture'],'All proposal scenarios must be supported.');
check(clean_language('id')==='id'&&clean_language('th')==='th','Indonesian and Thai validation failed.');
try{clean_language('ta');check(false,'Tamil must not be accepted.');}catch(InvalidArgumentException $e){check(true,'Tamil is rejected.');}
check(clean_confidence('0.72')===0.72,'Confidence parsing failed.');
try{clean_text(str_repeat('a',501),500);check(false,'Oversized text must fail.');}catch(InvalidArgumentException $e){check(true,'Oversized text rejected.');}

$requiredFiles=['index.php','business.php','api/translate.php','api/speech.php','api/assistance.php','api/glossary.php','api/report.php','api/analytics.php','api/tourist.php','api/business.php','api/public_business.php','api/insights.php','database/jomcommunicate.sql','database/migrations/20260828_document_features.sql'];
foreach($requiredFiles as $file)check(is_file($root.'/'.$file),'Missing required file: '.$file);
$all='';foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){$path=$file->getPathname();if($file->isFile()&&!str_contains($path,DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR)&&!str_contains($path,DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR))$all.=file_get_contents($path)."\n";}
check(!preg_match('/Tamil|ta-IN|ta-MY|value=["\']ta["\']/i',$all),'Tamil remains in application files.');
foreach(['Automatic detection','Two-way conversation','Report unclear','Indonesian','Thai','Medical','Emergency','Destination packs','Frequently asked questions','Export CSV'] as $needle)check(str_contains($all,$needle),'Missing proposal feature marker: '.$needle);
check(str_contains($all,'dark-mode')&&str_contains($all,'jomcommunicate_theme'),'Persistent dark mode is missing.');
check(str_contains($all,'topbar-title'),'Header title/date spacing class is missing.');
$login=file_get_contents($root.'/login.php');check(str_contains($login,'failed_login_attempts')&&str_contains($login,'INTERVAL 15 MINUTE'),'Login lockout is missing.');
$schema=file_get_contents($root.'/database/jomcommunicate.sql');foreach(['business_faqs','business_terms','malaysian_terms','phrase_packs','analytics_events','business_interactions'] as $table)check(str_contains($schema,'CREATE TABLE IF NOT EXISTS '.$table),'Schema table missing: '.$table);

if($failures){fwrite(STDERR,"FAILED ({$checks} checks)\n- ".implode("\n- ",$failures)."\n");exit(1);}echo "PASS: {$checks} application checks\n";
