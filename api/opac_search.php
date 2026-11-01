<?php
// Public OPAC endpoint: deterministic English/Filipino/Taglish search over stored catalog records only.
require_once __DIR__.'/config.php';
require_once __DIR__.'/catalog_search_helpers.php';
header('Content-Type: application/json; charset=utf-8'); header('Access-Control-Allow-Origin: *');
$q=trim((string)($_GET['q']??'')); $author=trim((string)($_GET['author']??'')); $isbn=trim((string)($_GET['isbn']??''));
$category=trim((string)($_GET['category']??'')); $yearFrom=($_GET['year_from']??'')!==''?(int)$_GET['year_from']:null; $yearTo=($_GET['year_to']??'')!==''?(int)$_GET['year_to']:null;
$onlyAvail=(($_GET['available']??'')==='1'); $limit=max(1,min(100,(int)($_GET['limit']??50)));
try{
 $books=supabaseRequest('books?select=*,categories(name)'); if(!is_array($books))$books=[];
 $cats=supabaseRequest('categories?select=id,name');$catMap=[];if(is_array($cats))foreach($cats as $c)if(isset($c['id']))$catMap[(string)$c['id']]=(string)($c['name']??'');
 $results=[];
 foreach($books as $b){
  $cat=$catMap[(string)($b['category_id']??'')]??($b['categories']['name']??'');
  if($author!==''&&stripos((string)($b['author']??''),$author)===false)continue;
  if($isbn!==''&&stripos((string)($b['isbn']??''),$isbn)===false)continue;
  if($category!==''&&stripos($cat,$category)===false)continue;
  if($yearFrom!==null&&(int)($b['year_published']??0)<$yearFrom)continue;
  if($yearTo!==null&&(int)($b['year_published']??0)>$yearTo)continue;
  if($onlyAvail&&(int)($b['available']??0)<=0)continue;
  $score=catalog_score_book($b,$q,$cat); if($score<=0)continue;
  $b['category_name']=$cat?:'Uncategorized';$b['score']=$score;$results[]=$b;
 }
 usort($results,function($a,$b){return ($b['score']<=>$a['score'])?:strcasecmp((string)($a['title']??''),(string)($b['title']??''));});
 $results=array_slice($results,0,$limit);
 jsonResponse(['ok'=>true,'count'=>count($results),'query'=>$q,'expanded'=>catalog_terms($q),'results'=>$results]);
}catch(Throwable $e){error_log('OPAC search failed: '.$e->getMessage());jsonResponse(['ok'=>false,'count'=>0,'query'=>$q,'results'=>[],'error'=>'Unable to search the catalog right now.'],500);}
?>