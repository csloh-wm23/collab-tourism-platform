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

$requiredFiles=['index.php','business.php','config/security.php','config/admin_pdf.php','assets/js/core.js','api/translate.php','api/speech.php','api/assistance.php','api/glossary.php','api/report.php','api/analytics.php','api/tourist.php','api/profile.php','api/business.php','api/public_business.php','api/insights.php','api/admin.php','database/jomcommunicate.sql','database/migrations/20260828_document_features.sql','database/migrations/20260905_profile_picture.sql','database/migrations/20260911_business_inbox.sql'];
foreach($requiredFiles as $file)check(is_file($root.'/'.$file),'Missing required file: '.$file);
$all='';foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){$path=$file->getPathname();if($file->isFile()&&!str_contains($path,DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR)&&!str_contains($path,DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR))$all.=file_get_contents($path)."\n";}
check(!preg_match('/\bTamil\b|\bta-(?:IN|MY)\b|value=["\']ta["\']/i',$all),'Tamil remains in application files.');
foreach(['Automatic detection','Two-way conversation','Report unclear','Indonesian','Thai','Medical','Emergency','Destination packs','Frequently asked questions','Export CSV'] as $needle)check(str_contains($all,$needle),'Missing proposal feature marker: '.$needle);
check(str_contains($all,'dark-mode')&&str_contains($all,'jomcommunicate_theme'),'Persistent dark mode is missing.');
check(str_contains($all,'topbar-title'),'Header title/date spacing class is missing.');
check(str_contains($all,'sidebarBackdrop')&&str_contains($all,'closeMenuButton'),'Dismissible navigation controls are missing.');
check(str_contains($all,"event.key==='Escape'")&&str_contains($all,"setMenu(false)"),'Keyboard and programmatic navigation dismissal are missing.');
check(str_contains($all,"addEventListener('hashchange'")&&str_contains($all,"history.pushState"),'Hash and browser-history navigation handling is missing.');
check(!str_contains($all,"'confidence'=>0.92")&&!str_contains($all,'Number(data.confidence||0)'),'A fabricated translation confidence remains.');
check(str_contains($all,'not_provided_by_google')&&str_contains($all,'confidenceLabel'),'Truthful translation-confidence handling is missing.');
check(str_contains($all,'twoWayLanguages')&&str_contains($all,"source==='auto'?detected:source"),'Automatic-source two-way switching is not covered.');
check(str_contains($all,'prepareNextTwoWayTurn(current,from)')&&str_contains($all,"direction={source:current.to,target:current.from}"),'Two-way mode must switch the next speaker back to the previous language.');
check(str_contains($all,"Choose the spoken language before using the microphone"),'Automatic voice input must not silently fall back to English.');
check(str_contains($all,'guest_preferences')&&str_contains($all,"consent_type='anonymous_analytics'"),'Server-side analytics consent verification is missing.');
check(str_contains($all,'source_hash')&&str_contains($all,'VALUES(NULL,NULL,NULL'),'Anonymous issue reporting is incomplete.');
check(str_contains($all,'business_profile_translations')&&str_contains($all,'toggle_item')&&str_contains($all,'update_item'),'Multilingual editable business content is incomplete.');
check(str_contains($all,'resubmit_application'),'Rejected business application resubmission is missing.');
$index=file_get_contents($root.'/index.php');
check(substr_count($index,'id="mainNavigation"')===1&&str_contains($index,'</aside>'),'Navigation aside markup is unbalanced.');
check(strpos($index,'</aside>')<strpos($index,'id="sidebarBackdrop"'),'The page must not be nested inside the hidden navigation drawer.');
$css=file_get_contents($root.'/assets/css/styles.css');
check(str_contains($css,'.profile-layout')&&str_contains($css,'.module-tabs'),'TourLingo profile and modular workspace styling is missing.');
check(str_contains($css,'.menu-button span')&&str_contains($css,'@media (max-width: 360px)'),'Responsive mobile navigation styling is missing.');
check(str_contains($index,'TourLingo')&&str_contains($index,'Profile & settings'),'TourLingo branding or profile management is missing.');
check(!str_contains($index,'id="communicationScenario"'),'The translator must not expose a tourism-scenario selector.');
check(str_contains($index,"if (\$user && \$role === 'tourist')")&&str_contains($index,"nav_button('communication', 'Translate', 'translate', !\$user)"),'Guest navigation must be limited to the translator.');
check(str_contains(file_get_contents($root.'/assets/js/app.js'),"defaultPage=window.JOM.authenticated?'home':'communication'")&&str_contains(file_get_contents($root.'/assets/js/app.js'),"translationScenario='culture'"),'Guest routing or neutral translation context is missing.');
check(str_contains($index,'id="voiceActive"')&&str_contains($index,'id="finishSpeaking"')&&str_contains($index,'Start speaking'),'Visible start and finish controls for microphone input are missing.');
check(str_contains($index,'aria-label="Message to translate"'),'The translator message field needs an accessible name.');
check(str_contains($index,'role="dialog"')&&str_contains($index,'aria-modal="true"'),'The large-screen message must be exposed as a modal dialog.');
$appJs=file_get_contents($root.'/assets/js/app.js');
check(str_contains($appJs,'r.continuous=true')&&str_contains($appJs,'r.interimResults=true')&&str_contains($appJs,"addEventListener('click',finishRecognition)"),'Continuous microphone capture must remain active until the user finishes it.');
check(str_contains($appJs,'Delete saved item')&&str_contains($appJs,'Add to favourites'),'Saved-record icon controls need accessible labels.');
check(substr_count($all,'styles.css?v=')>=4&&str_contains($index,'width="19" height="19"'),'Updated interface styles must bypass stale browser caches and constrain the microphone icon.');
check(str_contains($index,'id="largeMessage" class="secondary"'),'Large-screen message action must retain the neutral button style.');
check(str_contains($css,'.emergency-overlay')&&str_contains($css,'background: #000;'),'Large-screen message display must use a black background.');
check(str_contains($css,'[hidden] { display: none !important; }'),'Hidden loading and conditional content must not be forced visible by component CSS.');
check(str_contains($css,'.public-business summary')&&str_contains($css,'min-height: 44px;'),'Public FAQ rows must remain large enough for touch interaction.');
check(str_contains($css,'.swap-button { margin: 0 auto; transform: rotate(90deg); width: 44px; }'),'The mobile language-swap control must remain a full-size touch target.');
check(str_contains($index,"\$businessStatus === 'rejected' ? 'Needs revision' : 'Under review'")&&str_contains($css,'.status-pill.rejected'),'Rejected applications need an accurate, styled status badge.');
$businessPage=file_get_contents($root.'/business.php');
check(str_contains($businessPage,"localStorage.getItem('jomcommunicate_theme')")&&str_contains($businessPage,'showLoadError'),'The public business page must preserve theme and recover from load errors.');
check(str_contains($index,'exportAdminReport')&&str_contains($index,'exportAdminPdf')&&str_contains($index,'adminRoleReport')&&str_contains($index,'adminActivity'),'Administration reporting interface is missing.');
check(str_contains($index,'profilePictureInput')&&str_contains($index,'uploadProfilePicture'),'Profile picture controls are missing.');
$login=file_get_contents($root.'/login.php');check(str_contains($login,'failed_login_attempts')&&str_contains($login,'INTERVAL 15 MINUTE'),'Login lockout is missing.');
$profile=file_get_contents($root.'/api/profile.php');check(str_contains($profile,'update_account')&&str_contains($profile,'change_password')&&str_contains($profile,'password_verify'),'Account profile or secure password management is missing.');
check(str_contains($profile,'move_uploaded_file')&&str_contains($profile,'FILEINFO_MIME_TYPE')&&str_contains($profile,'getimagesize')&&str_contains($profile,'2 * 1024 * 1024'),'Secure profile-picture upload validation is missing.');
$adminApi=file_get_contents($root.'/api/admin.php');check(str_contains($adminApi,'export_admin_csv')&&str_contains($adminApi,'export_admin_pdf')&&str_contains($adminApi,'users_by_role')&&str_contains($adminApi,'recent_activity'),'Administration CSV/PDF reporting is incomplete.');
check(str_contains($index,'conversationTimeline')&&str_contains($appJs,'conversationMessages'),'Continuous two-person conversation history is missing.');
check(str_contains($index,'businessInbox')&&str_contains(file_get_contents($root.'/api/business.php'),'update_inquiry'),'Business question inbox and reply workflow is missing.');
check(str_contains($index,'insightComparisons')&&str_contains(file_get_contents($root.'/api/insights.php'),'previous_from'),'Period comparison insights are missing.');
check(str_contains($index,'saveEmergencyCard')&&str_contains($appJs,'emergencyKey'),'Emergency card workflow is missing.');
$schema=file_get_contents($root.'/database/jomcommunicate.sql');foreach(['business_profile_translations','business_faqs','business_terms','malaysian_terms','phrase_packs','user_saved_packs','analytics_events','business_interactions'] as $table)check(str_contains($schema,'CREATE TABLE IF NOT EXISTS '.$table),'Schema table missing: '.$table);
check(str_contains($schema,'profile_image VARCHAR(255) NULL'),'Profile picture schema column is missing.');
foreach(['restaurant','hotel','transport','shopping','medical','emergency'] as $scenario)check(substr_count($schema,"'Malaysia','{$scenario}','ms'")>=3,"Assistant workflow seed is incomplete for {$scenario}.");

if($failures){fwrite(STDERR,"FAILED ({$checks} checks)\n- ".implode("\n- ",$failures)."\n");exit(1);}echo "PASS: {$checks} application checks\n";
