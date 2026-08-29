<?php
declare(strict_types=1);
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/validation.php';

$user=current_user();
if(!$user||!in_array((string)($user['role']??''),['editor','admin'],true)||($user['status']??'')!=='active'){
    http_response_code(403);header('Content-Type: application/json');exit(json_encode(['ok'=>false,'message'=>'Active editor or administrator access required.']));
}

function optional_filter(string $key,int $maximum):string{
    $value=trim((string)($_GET[$key]??''));
    if($value==='')return '';
    return clean_text($value,$maximum);
}

try{
    $db=database();
    $from=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['from']??''))?(string)$_GET['from']:'2000-01-01';
    $to=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['to']??''))?(string)$_GET['to']:date('Y-m-d');
    if($from>$to)throw new InvalidArgumentException('The start date must not be after the end date.');
    $language=optional_filter('language',12);if($language!=='')$language=clean_language($language);
    $scenario=optional_filter('scenario',40);if($scenario!=='')$scenario=clean_scenario($scenario);
    $location=optional_filter('location',120);$businessType=optional_filter('business_type',80);

    $eventWhere=['created_at>=?','created_at<DATE_ADD(?,INTERVAL 1 DAY)'];$eventParams=[$from,$to];
    foreach([['language_code',$language],['scenario',$scenario],['location_label',$location],['business_type',$businessType]] as [$column,$value])if($value!==''){$eventWhere[]="$column=?";$eventParams[]=$value;}
    $eventSql='SELECT event_type,COALESCE(language_code,"—") language_code,COALESCE(scenario,"—") scenario,COALESCE(location_label,"—") location_label,COALESCE(business_type,"—") business_type,COALESCE(term_label,"—") term_label,COUNT(*) total FROM analytics_events WHERE '.implode(' AND ',$eventWhere).' GROUP BY event_type,language_code,scenario,location_label,business_type,term_label ORDER BY total DESC';
    $event=$db->prepare($eventSql);$event->execute($eventParams);$events=$event->fetchAll();

    $reportWhere=['created_at>=?','created_at<DATE_ADD(?,INTERVAL 1 DAY)'];$reportParams=[$from,$to];
    if($language!==''){$reportWhere[]='target_language=?';$reportParams[]=$language;}if($scenario!==''){$reportWhere[]='scenario=?';$reportParams[]=$scenario;}
    $reportSql='SELECT issue_type,COALESCE(target_language,"—") language_code,COALESCE(scenario,"—") scenario,COALESCE(term_label,"—") term_label,COUNT(*) total,ROUND(AVG(confidence),2) average_confidence FROM translation_reports WHERE '.implode(' AND ',$reportWhere).' GROUP BY issue_type,target_language,scenario,term_label ORDER BY total DESC';
    $reports=$db->prepare($reportSql);$reports->execute($reportParams);$issues=$reports->fetchAll();

    $interactionWhere=['i.created_at>=?','i.created_at<DATE_ADD(?,INTERVAL 1 DAY)'];$interactionParams=[$from,$to];
    if($language!==''){$interactionWhere[]='i.language_code=?';$interactionParams[]=$language;}if($businessType!==''){$interactionWhere[]='b.category=?';$interactionParams[]=$businessType;}
    $repeated=$db->prepare('SELECT i.question_label,COUNT(*) total FROM business_interactions i JOIN businesses b ON b.id=i.business_id WHERE '.implode(' AND ',$interactionWhere).' AND i.question_label IS NOT NULL AND i.question_label<>"" GROUP BY i.question_label HAVING total>1 ORDER BY total DESC LIMIT 20');$repeated->execute($interactionParams);$questions=$repeated->fetchAll();
    $businessAnalysis=$db->prepare('SELECT b.category business_type,i.category communication_category,COUNT(*) total FROM business_interactions i JOIN businesses b ON b.id=i.business_id WHERE '.implode(' AND ',$interactionWhere).' GROUP BY b.category,i.category ORDER BY total DESC');$businessAnalysis->execute($interactionParams);$businessRows=$businessAnalysis->fetchAll();
    $peak=$db->prepare('SELECT HOUR(created_at) hour_of_day,COUNT(*) total FROM analytics_events WHERE '.implode(' AND ',$eventWhere).' GROUP BY HOUR(created_at) ORDER BY total DESC LIMIT 8');$peak->execute($eventParams);$peakRows=$peak->fetchAll();

    $recordWhere=['created_at>=?','created_at<DATE_ADD(?,INTERVAL 1 DAY)',"record_type='translation'"];$recordParams=[$from,$to];if($language!==''){$recordWhere[]='target_language=?';$recordParams[]=$language;}if($scenario!==''){$recordWhere[]='scenario=?';$recordParams[]=$scenario;}
    $translationCount=$db->prepare('SELECT COUNT(*) FROM records WHERE '.implode(' AND ',$recordWhere));$translationCount->execute($recordParams);
    $stats=['translations'=>(int)$translationCount->fetchColumn(),'phrases'=>(int)$db->query("SELECT (SELECT COUNT(*) FROM records WHERE record_type='phrase')+(SELECT COUNT(*) FROM business_phrases)")->fetchColumn(),'businesses'=>(int)$db->query("SELECT COUNT(*) FROM businesses WHERE verification_status='approved'")->fetchColumn(),'pending_businesses'=>(int)$db->query("SELECT COUNT(*) FROM businesses WHERE verification_status='pending'")->fetchColumn(),'unclear_reports'=>array_sum(array_map(fn($r)=>(int)$r['total'],$issues))];

    $recommendations=[];
    if($issues){$top=$issues[0];$recommendations[]='Review '.$top['scenario'].' content for '.$top['language_code'].'; it has '.$top['total'].' reported '.$top['issue_type'].' issue(s).';}
    $difficult=array_values(array_filter($issues,fn($row)=>$row['term_label']!=='—'));if($difficult){$recommendations[]='Clarify the Malaysian term “'.$difficult[0]['term_label'].'” in the relevant phrase pack and glossary.';}
    if($questions){$recommendations[]='Create an approved business quick reply for “'.$questions[0]['question_label'].'” (asked '.$questions[0]['total'].' times).';}
    if($businessRows){$top=$businessRows[0];$recommendations[]='Prioritise '.$top['communication_category'].' support for '.$top['business_type'].' businesses based on '.$top['total'].' interaction(s).';}
    if(!$recommendations)$recommendations[]='No recurring issue is visible in the selected anonymous data. Continue collecting consented events.';

    $filters=['from'=>$from,'to'=>$to,'language'=>$language,'scenario'=>$scenario,'location'=>$location,'business_type'=>$businessType];
    if(($_GET['format']??'')==='csv'){
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="jomcommunicate-anonymous-report.csv"');$out=fopen('php://output','w');
        fputcsv($out,['section','dimension 1','dimension 2','dimension 3','dimension 4','dimension 5','total/value']);
        foreach($events as $row)fputcsv($out,['event',$row['event_type'],$row['language_code'],$row['scenario'],$row['location_label'],$row['business_type'].' / '.$row['term_label'],$row['total']]);
        foreach($issues as $row)fputcsv($out,['issue',$row['issue_type'],$row['language_code'],$row['scenario'],$row['term_label'],'average confidence',$row['average_confidence']??'unavailable']);
        foreach($questions as $row)fputcsv($out,['repeated enquiry',$row['question_label'],'','','','',$row['total']]);
        foreach($businessRows as $row)fputcsv($out,['business analysis',$row['business_type'],$row['communication_category'],'','','',$row['total']]);
        foreach($peakRows as $row)fputcsv($out,['peak period',$row['hour_of_day'].':00','','','','',$row['total']]);
        foreach($recommendations as $recommendation)fputcsv($out,['recommendation',$recommendation,'','','','','']);
        fclose($out);exit;
    }
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    echo json_encode(['ok'=>true,'stats'=>$stats,'events'=>$events,'issues'=>$issues,'repeated_questions'=>$questions,'business_analysis'=>$businessRows,'peak_periods'=>$peakRows,'recommendations'=>$recommendations,'filters'=>$filters],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(InvalidArgumentException $e){http_response_code(422);header('Content-Type: application/json');echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Insights: '.$e->getMessage());http_response_code(500);header('Content-Type: application/json');echo json_encode(['ok'=>false,'message'=>'Insights are temporarily unavailable.']);}
