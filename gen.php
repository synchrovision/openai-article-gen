<?php
if(substr($_SERVER['SERVER_PROTOCOL']??'',0,4)==='HTTP'){die("Execute this PHP with CLI !");}
ini_set("error_log","php://stdout");
ini_set('max_execution_time', 0);
chdir(__DIR__);
if(!is_dir('csv')){mkdir('csv');}
$opts=getopt('c::than:l:',['first-id:','pref:','from:','to:','help']);

$tasks=[
	'c'=>[
		'loop'=>function($h,$opts){
			$concept=$opts['c']?:'AIが実現する未来';
			$results=$h->do_prompt(sprintf(
				"「%s」というコンセプトで、ブログ記事のカテゴリを%d個考えてください。それぞれのカテゴリに半角英数20文字以内のスラッグを併せて書いてください。",
				$concept,
				$opts['n']??5
			));
			$id=(int)($opts['first-id']??1);
			$csv=fopen('csv/categories.csv','w');
			fputcsv($csv,['id','name','slug','parent']);
			yield sprintf("Generating categories for concept '%s'",$concept);
			foreach(explode("\n",$results) as $line){
				if(
					preg_match("/^(\d+\.?)?[\s「\"]*(.+?)[\s\-: 　」\"]*([\w\-]+)$/u",$line,$matches) || 
					preg_match("/^(\d+\.?)?[\s「\"]*(.+?)[\s\-: 　」\"]*\(([\w\-]+)\)$/u",$line,$matches) || 
					preg_match("/^(\d+\.?)?[\s「\"]*(.+?)[\s\-: 　」\"]*\(slug: ([\w\-]+)\)$/u",$line,$matches)
				){
					fputcsv($csv,[$id++,$matches[2],str_replace('-','_',$matches[3]),0]);
				}
				else{
					yield sprintf("Can not parse line '%s'",$line);
				}
			}
			fclose($csv);
			yield "Finish! Generated categories.csv";
		}
	],
 	't'=>[
		'loop'=>function($h,$opts){
			$categories=get_categories_data();
			$csv=fopen('csv/titles.csv','w');
			fputcsv($csv,['id','category','title','slug']);
			$id=(int)($opts['first-id']??1);
			foreach($categories as $category_id=>$category){
				if(!empty($category['has_children'])){continue;}
				if(!empty($opts['from']) && $category_id<$opts['from']){continue;}
				if(!empty($opts['to']) && $category_id>$opts['to']){continue;}
				$cat=implode(' ',array_map(
					function($id)use($categories){
						return $categories[$id]['name'];
					},
					array_merge($category['parents']??[],[$category_id])
				));
				yield sprintf("Generating titles for category '%s'",$cat);
				$prompt=sprintf(
					"「%s」のカテゴリの記事のタイトルを%d個考えてください。それぞれのタイトルに半角英数20文字以内のスラッグを併せて書いてください。",
					$cat,
					$opts['n']??5
				);
				$results=$h->do_prompt($prompt);
				foreach(explode("\n",$results) as $line){
					if(
						preg_match("/^(\d+\.?)?[\s「\"]*(.+?)[\s\-: 　」\"]*([\w\-]+)$/u",$line,$matches) || 
						preg_match("/^(\d+\.?)?[\s「\"]*(.+?)[\s\-: 　」\"]*\(([\w\-]+)\)$/u",$line,$matches) || 
						preg_match("/^(\d+\.?)?[\s「\"]*(.+?)[\s\-: 　」\"]*\(slug: ([\w\-]+)\)$/u",$line,$matches)
					){
						fputcsv($csv,[$id++,$category_id,$matches[2],str_replace('-','_',$matches[3])]);
					}
					else{
						yield sprintf("Can not parse line '%s'",$line);
					}
				}
			}
			fclose($csv);
			yield "Finish! Generated titles.csv";
		}
	],
	'h'=>[
		'loop'=>function($h,$opts){
			$categories=get_categories_data();
			$titles=get_titles_data();
			$csv=fopen('csv/headings.csv','w');
			fputcsv($csv,['id','heading']);
			$id=(int)($opts['first-id']??1);
			foreach($titles as $article_id=>$article_data){
				if(!empty($opts['from']) && $article_id<$opts['from']){continue;}
				if(!empty($opts['to']) && $article_id>$opts['to']){continue;}
				$cat=implode(' ',array_map(
					function($id)use($categories){
						return $categories[$id]['name'];
					},
					array_merge($categories[$article_data['category']]['parents']??[],[$article_data['category']])
				));
				yield sprintf("Generating headings for article '%s'",$article_data['title']);
				$prompt=sprintf(
					"「%s」のカテゴリの「%s」というタイトルの記事の%d個の段落の見出しを考えてください。",
					$cat,$article_data['title'],
					$opts['n']??5
				);
				$results=$h->do_prompt($prompt);
				foreach(explode("\n",$results) as $line){
					if(preg_match("/^(\d+\.?)?\s*(.+?)$/",$line,$matches)){
						fputcsv($csv,[$article_id,$matches[2]]);
					}
					else{
						yield sprintf("Can not parse line '%s'",$line);
					}
				}
			}
			fclose($csv);
			yield "Finish! Generated headings.csv";
		}
	],
	'a'=>[
		'loop'=>function($h,$opts){
			$categories=get_categories_data();
			$titles=get_titles_data();
			$headings=get_headings_data();
			$total=count($titles);
			$count=1;
			if(!is_dir('md')){mkdir('md');}
			foreach($titles as $article_id=>$article_data){
				if(!empty($opts['from']) && $article_id<$opts['from']){continue;}
				if(!empty($opts['to']) && $article_id>$opts['to']){continue;}
				$md=fopen(sprintf('md/%s-%d.md',$opts['pref']??'article',$article_id),'w');
				$cat=implode(' ',array_map(
					function($id)use($categories){
						return $categories[$id]['name'];
					},
					array_merge($categories[$article_data['category']]['parents']??[],[$article_data['category']])
				));
				yield sprintf("Generating article '%s' %d/%d",$article_data['title'],$count++,$total);
				foreach($headings[$article_id] as $heading){
					$prompt=sprintf(
						"「%s」のカテゴリの「%s」というタイトルの記事の「%s」という見出しの段落の本文を%d文字前後で書いてください。",
						$cat,$article_data['title'],$heading,
						$opts['l']??1000
					);
					$result=$h->do_prompt($prompt);
					fwrite($md,sprintf("### %s\n\n%s\n\n",$heading,$result));
				}
				fclose($md);
			}
			yield sprintf("Finish! Generated %d MarkDown files",$total);
		}
	],
	'help'=>false
];
foreach($tasks as $action=>$task){
	if(isset($opts[$action])){break;}
}
if($action==='help'){
	echo "Article Generator using OpenAI API";
	return;
}
$h=new OpenAiHandler(parse_ini_file('.env'));
if($action==='test'){
	$task($h,$opts);
	return;
}
foreach($task['loop']($h,$opts) as $message){
	echo $message."\n";
}
function get_categories_data(){
	$csv=fopen('csv/categories.csv','r');
	$data=[];
	$keys=fgetcsv($csv);
	while($row=fgetcsv($csv)){
		$item=array_combine($keys,$row);
		$data[]=$item;
	}
	$data=array_column($data,null,'id');
	foreach($data as $id=>$item){
		if(empty($item['parent'])){continue;}
		$parent_id=$item['parent'];
		$parents=[];
		while(isset($data[$parent_id])){
			array_unshift($parents,$parent_id);
			if(isset($data[$parent_id]['parents'])){
				$parents=array_merge($data[$parent_id]['parents'],$parents);
				break;
			}
			$data[$parent_id]['has_children']=true;
			$parent_id=$data[$parent_id]['parent'];
		}
		$data[$id]['parents']=$parents;
	}
	return $data;
}
function get_titles_data(){
	$csv=fopen('csv/titles.csv','r');
	$data=[];
	$keys=fgetcsv($csv);
	while($row=fgetcsv($csv)){
		$item=array_combine($keys,$row);
		$data[]=$item;
	}
	$data=array_column($data,null,'id');
	return $data;
}
function get_headings_data(){
	$csv=fopen('csv/headings.csv','r');
	$data=[];
	$keys=fgetcsv($csv);
	while($row=fgetcsv($csv)){
		$item=array_combine($keys,$row);
		$data[$item['id']][]=$item['heading'];
	}
	return $data;
}
class OpenAiHandler{
	private $ch;
	function __construct($env){
		$this->ch=curl_init();
		curl_setopt_array($this->ch,[
			CURLOPT_URL=>"https://api.openai.com/v1/chat/completions",
			CURLOPT_RETURNTRANSFER=>1,
			CURLOPT_POST=>1,
			CURLOPT_HTTPHEADER=>[
				"Content-Type: application/json",
				"Authorization: Bearer {$env['OPENAI_API_KEY']}"
			]
		]);
	}
	function do_prompt($prompt){
		curl_setopt($this->ch,CURLOPT_POSTFIELDS,json_encode([
			'model'=>'gpt-3.5-turbo',
			'messages'=>[
				['role'=>'user','content'=>$prompt]
			],
			'temperature'=>0.7
		]));
		$result=json_decode(curl_exec($this->ch),true);
		return $result['choices'][0]['message']['content'];
	}
}