<?php

/*

RxNormRef
=========
A tool to access the NIH's database of semantic medical terms. Uses the rxNorm_php api library maintained by Code For America.

How to Install/Use
==================
Upload to web server, call rxNormRef.php (can also optionally be named to index.php).


*/

require 'APIBaseClass.php';
require 'rxNormApi.php';
define('PROGRESSIVE_LOAD', true);
// progressive load doesnt load cached pages 'progressively'
define('CACHE_QUERY',false);
// caching requires progressive load to be true
// make sure this folder has proper permissions
define('CACHE_STORE','cache_query/');

class rxNormRef extends rxNormApi{

	function __construct(){
		parent::__construct();
		// putting footer in here to reduce the amount of data stored in cached files
		// when a cached file is found then the script dies that output (and this footer + stats) 
		// preventing all subsequent api calls.
		$this->start_time = (float) array_sum(explode(' ',microtime()));
		$this->oh_memory = round(memory_get_usage() / 1024);
		$this->footer = "
		<div id = 'help'>
			<h3>Where to start?</h3>
			<p>An RXCUI refers to a record which can refer to a drug, or a drug concept.</p>
			<p>First type in the name of a concept or drug, to look up the RXCUI, and select the type of search query to perform from the drop down menu.</p>
			<h3>About</h3>
			<p>Built with PHP5, and the <a href='https://github.com/codeforamerica/rxNorm_php'>rxNorm_php api library</a> to access the <a href='http://www.nlm.nih.gov/'>NiH databases</a>.</p>
		</div>
		";


		if(PROGRESSIVE_LOAD == true){
   	 	    @apache_setenv('no-gzip', 1);
			@ini_set('zlib.output_compression', 0);
			@ini_set('implicit_flush', 1);
			for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
			flush();
			ob_implicit_flush(1);
			ob_start();
		}
		// process any post if existant
		if($_POST) self::post_check();
		// if we haven't died by now then close and flush the ob cache for the final time
		self::ob_cacher(1);
		// echo the footer and stats to screen.
		echo $this->footer .  self::stats() . "\n</body>\n\t</html>";

	}

	function post_check(){
		self::ob_cacher();
		if($_POST['rxcui']) {
			switch($_POST['action']){
				case 'AllRelatedInfo':
					$xml = new SimpleXMLElement($this->getAllRelatedInfo($_POST['rxcui']));
					self::list_2d_xmle($xml->allRelatedGroup->conceptGroup);
				break;
				case 'RxConceptProperties':
					$xml = new SimpleXMLElement($this->getRxConceptProperties($_POST['rxcui']));
					self::list_2d_xmle($xml->allRelatedGroup->conceptGroup);
				break;
				case 'NDCs':
					$xml = new SimpleXMLElement($this->getNDCs($_POST['rxcui']));
					self::list_2d_xmle($xml->ndcGroup->ndcList);
				break;
			}
			unset($xml);
		}
		if($_POST['searchTerm'] && $_POST['action']== 'SearchTerm') {
		// look up inside of defined cache location
			self::ob_cacher();
			$xml = new SimpleXMLElement($this->findRxcuiByString($_POST['searchTerm']));
			$id = $xml->idGroup->rxnormId;
			if($id != '') {
				echo '<p>Term "<b>'. $_POST['searchTerm'] . '</b>" matches RXCUI: <b>' .$id . "</b></p>\n" ;
				self::ob_cacher();
			}
			else{
				$search = new SimpleXMLElement($this->getSpellingSuggestions($_POST['searchTerm']));
				echo '<h3>Term <b>"'. $_POST['searchTerm'].'"</b> not found</h3>
				<h4>Did you mean?</h4>' ;
				foreach($search->suggestionGroup->suggestionList->suggestion as $loc=>$value)
					if($loc==0) $first = $value;
						echo "\n\t$value\t\n";
					
				echo '<br><b>Showing first sugestion '.$first.'</b>';
				unset($search);
				$xml= new SimpleXMLElement($this->findRxcuiByString("$first"));
				$id= $xml->idGroup->rxnormId;
				unset($xml);
			}
			$xml = new SimpleXMLElement($this->getAllRelatedInfo($id));
			self::list_2d_xmle($xml->allRelatedGroup->conceptGroup);
			self::ob_cacher();	
			unset($xml);
		}
		
		if($_POST['searchTerm'] && $_POST['action']== 'Drugs') {
			$xml = new SimpleXMLElement($this->getDrugs($_POST['searchTerm']));
			self::list_2d_xmle($xml->drugGroup->conceptGroup);
			unset($xml);
		}
	}
	
	function xmle_table_row($key,$value,$key_css_class='property',$value_css_class='value'){
		$normalElements = Array(
			'TTY'=>'Term Type',
			'IN'=>'Ingredient',
			'PIN'=>'Precise Ingredient',
			'MIN'=>'Multiple Ingredients',
			'DF'=>'Dose Form',
			'SCDC'=>'Semantic Clinical Drug Component',
			'SCDF'=>'Semantic Clinical Drug Form',
			'BN'=>'Brand Name',
			'SBDC'=>'Semantic Branded Drug Form',
			'SBDF'=>'Semantic Branded Drug Form',
			'SBD'=>'Semantic Branded Drug',
			'SY'=>'Term Type',
			'TMSY'=>'Term Type',
			'BPCK'=>'Brand Name Pack',
			'GPCK'=>'Generic Pack');
		return "\n\t<ul>\n\t\t<li class='$key_css_class'>".($normalElements[strtoupper($key)]?$normalElements[strtoupper($key)]:$key)."</li>".($key!='tty' && $value != '' ? "\n\t\t<li class='$value_css_class'>".($normalElements[strtoupper($value)]?$normalElements[strtoupper($value)]:$value)."</li>":NULL)."\n\t\t\t</ul>\n";
	}
	
	function cache_token(){
	// generates file name for cache storage
		if($_POST){
			foreach($_POST as $value)
				$token []= str_replace(' ','',trim($value));
			return implode('_',$token);
		}
	}
	
	function ob_cacher($stop=NULL){
		global $footer;
		if(PROGRESSIVE_LOAD == true){		
			if(CACHE_QUERY == true && $stop == 1){
				$put_file = CACHE_STORE . self::cache_token();
				//die($put_file);
				$cache = ob_get_flush();
				if(!file_exists($put_file))
					file_put_contents("$put_file", $cache);
				}
				elseif(CACHE_QUERY != true){
				ob_end_flush();
				if($stop!= NULL ) ob_start();	
			}elseif($_POST && CACHE_QUERY == TRUE){
				$cache_token = CACHE_STORE.self::cache_token();
				//die($cache_token);
				if(file_exists($cache_token)){
					echo( file_get_contents($cache_token));
					echo $this->footer .   self::stats(1) ; 
					die( '</body></html>');
				}
			}
		}		
	}

	function list_2d_xmle($xml){
		if($xml->properties)
		// for the concept properties processing or xml elements that do not contain more xmlelements
			foreach($xml->properties as $value){
				$result .="<tr>";
				foreach($value as $key=>$value2)
					$result .=  ($value2 != '' ? "<td>" .self::xmle_table_row($key,$value2) . "</td>": NULL);
				$result .="</tr>";
			}
		else{
			foreach($xml as $value){
			// second row avoids displaying the parameter name for subsequent rows
			$second_row = false;
				foreach($value as $key=>$value2){
					if($key =='conceptProperties'){
						foreach($value2 as $key3=>$value3)
						// getting rid of column values that are redudant and LANGUAGE
							if($key3 != 'tty' && $key3 != 'suppress' && $key3 != 'language'){
									echo ($key3=='rxcui'?'<hr>':NULL) . "<ul><li>" .($second_row==true?self::xmle_table_row(NULL,$value3):self::xmle_table_row($key3,$value3)) .  '</li></ul>' ;
									self::ob_cacher();
							}
						$second_row = true;	
					}elseif($value->conceptProperties && $value2 != ''){
						echo "<ul>" .self::xmle_table_row(($key != 'tty'?$key:$value2),($key != 'tty'?$value2:NULL)). '</ul>';
						self::ob_cacher();
						}
				}	
		}
		}
		unset($xml);
		unset($result);
	}
	function stats($cache=false){
		return ($cache!=false?'<b><small>Rendering from cache</small></b><br>':NULL)."<b>Memory use: " . round(memory_get_usage() / 1024) . 'k'. "</b><br><b>Load time : "
	. sprintf("%.4f", (((float) array_sum(explode(' ',microtime())))-$this->start_time)) . " seconds</b><br><b>Overhead memory : ".$this->oh_memory." k</b>";

	}
}
echo '
<html>
	<head>
		<title>RxNorm Reference</title>
		<link rel="stylesheet" type="text/css" href="main.css" />
	</head>
	<body>
	<div id = "header">
		<h1>RxNorm Reference</h1>
	</div>
	<form method="post">
		<fieldset>
			<legend>Search </legend>
			<input type="text" name="searchTerm"/>
			<select name="action">
				<option value="SearchTerm">RXCUID</option>
				<option value="Drugs">Drugs</option>
				</select>
			<input type ="submit">
		</fieldset>
	</form>
	<form method="post">
		<fieldset>
			<legend>Lookup RXCUI</legend>
			<label>Enter RXCUI</label>
			<input type="text" name="rxcui" value="'.($_POST['rxcui']?$_POST['rxcui']:NULL).'"/>
			
			<select name="action">
			  	<option value="AllRelatedInfo">All Related Info</option>
				<option value="NDCs">NDCs</option>
				<option value="RxConceptProperties">Concept Properties</option>
				<option value="Strength">Strength</option>
				<option value="Quantity">Quantity</option>
				<option value="UNII">UNII</option>
				<option value="findRemapped">Find Remapped RXCUI</option>
				<option value="SplSetId">Look up special set ID</option>
				</select>				
			<input type ="submit">
		</fieldset>
		</form>';
//	self::ob_cacher();
 new RxNormRef;
