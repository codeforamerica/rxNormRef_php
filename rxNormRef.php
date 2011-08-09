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
// shows the umlscui column, enabled by default
define('SHOW_UML',false);
// force 'synonyml column to show (for debugging)
// Special handling of synonym column is required since they only appear to have values in all but two concept groups 
define('SHOW_ALL_SYNONYM',false);
// changing this will def. mess up the included template
define('SHOW_RXCUI',true);
define('SHOW_NAME',true);
// these still work, but will change the layout signfigantly depending on total number of columns
// these are all very redudant to display so disabled by default
define('SHOW_LANGUAGE',false);
define('SHOW_TTY',false);
define('SHOW_SUPPRESS',false);
// use if you're a data minor and have written another xml stucture... the 
// rxNorm structure is verbose and very embeded, it could definately use a shift
define('SHOW_ALL',false);

class rxNormRef extends rxNormApi{

	function __construct(){
		parent::__construct();
		// putting footer in here to reduce the amount of data stored in cached files
		// when a cached file is found then the script dies that output (and this footer + stats) 
		// preventing all subsequent api calls.
		$this->start_time = (float) array_sum(explode(' ',microtime()));
		$this->oh_memory = round(memory_get_usage() / 1024);
		$this->footer = "\n\t<div id = 'help'>\n\t\t<h3>Where to start?</h3>\n\t\t<p>An RXCUI refers to a record which can refer to a drug, or a drug concept.</p>\n\t\t<p>First type in the name of a concept or drug, to look up the RXCUI, and select the type of search query to perform from the drop down menu.</p>\n\t<h3>About</h3>\n\t\t<p>Built with PHP5, and the <a href='https://github.com/codeforamerica/rxNorm_php'>rxNorm_php api library</a> to access the <a href='http://www.nlm.nih.gov/'>NiH databases</a>.</p>\n\t</div>
		";
		// set up the 'filter' variable to determine what columns to show
		if(SHOW_ALL == FALSE){
			if(SHOW_LANGUAGE == false  ) $this->c_filter []='language';
			if(SHOW_SUPPRESS == false) $this->c_filter []='suppress';
			if(SHOW_RXCUI == false) $this->c_filter []='rxcui';
			if(SHOW_NAME == false) $this->c_filter []='name';
			if(SHOW_ALL_SYNONYM == FALSE) $this->c_filter []= 'synonym';
			if(SHOW_TTY == false) $this->c_filter []='tty';
			if(SHOW_UML == false) $this->c_filter []= 'umlscui';
		}
		// of course I could make a checkbox panel to allow for any combination of display fields, and cache entire returned xml results to do manipulations

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
		echo $this->footer . '<div id="stats">' . self::stats().'</div>' . "\n</div>\n\t</body>\n</html>";

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
			$xml = new SimpleXMLElement($this->findRxcuiByString($_POST['searchTerm']));
			$id = $xml->idGroup->rxnormId;
			if($id != '') {
				echo '<p class="term_result">Term "<b>'. $_POST['searchTerm'] . '</b>" matches RXCUI: <b>' .$id . "</b></p>\n" ;
			}
			else{
				$search = new SimpleXMLElement($this->getSpellingSuggestions($_POST['searchTerm']));
				echo '<p class="term_result"><h3>Term <b>"'. $_POST['searchTerm'].'"</b> not found</h3>
				<h4>Did you mean?</h4>' ;
				foreach($search->suggestionGroup->suggestionList->suggestion as $loc=>$value)
					if($loc==0) $first = $value;
						echo "\n\t<p class='suggestion'>$value</p>\t\n";
				echo '</p><p><b>Showing first sugestion '.$first.'</b></p>';
				unset($search);
				$xml= new SimpleXMLElement($this->findRxcuiByString("$first"));
				$id= $xml->idGroup->rxnormId;
				unset($xml);
			}
			$xml = new SimpleXMLElement($this->getAllRelatedInfo($id));
			self::list_2d_xmle($xml->allRelatedGroup->conceptGroup);
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

// return only the value in a  single element list?? or bypas this completely?
//		if($key ==null) return
		return  "\n\t\t<ul>\n\t\t\t<li class='$key_css_class'>".($normalElements[strtoupper($key)]?$normalElements[strtoupper($key)]:$key)."</li>".($key!='tty' && $value != '' ? "\n\t\t\t<li class='$value_css_class'>".($normalElements[strtoupper($value)]?$normalElements[strtoupper($value)]:$value)."</li>":NULL)."\n\t\t</ul>\n";
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
		if(PROGRESSIVE_LOAD == true){		
			if(CACHE_QUERY == true && $stop == 1){
				$put_file = CACHE_STORE . self::cache_token();
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
				$result .="\t<ul>";
				foreach($value as $key=>$value2)
					$result .=  ($value2 != '' ? "<li>" .self::xmle_table_row(NULL,$value2) . "</li>": NULL);
				$result .="\t</ul>";
			}
		else{
			foreach($xml as $value){
			// second row avoids displaying the parameter name for subsequent rows
			$second_row = false;
			// parent name is used to determine what columns to display (rather than parse the xml object)
			$parent_name = '';
				foreach($value as $key=>$value2){
					if($key =='conceptProperties'){
						foreach($value2 as $key3=>$value3){
						// getting rid of column values that are redudant and LANGUAGE, also only showing the synoym columns for SCD and SBD types .. but these may exist in  other queries (possibly..)
							if((!in_array($key3,$this->c_filter) )  || ($key3 == 'synonym' && in_array(strtoupper($parent_name),array( 'SCD','SBD')))){
//								if($second_row == true) echo "<ul><li><ul><li>$value3</li><li></li></ul></li></ul>";
//								else
								echo ($key3=='rxcui'?'<hr>':NULL). "\n<ul>\n\t<li>" .($second_row==true?self::xmle_table_row(NULL,$value3):self::xmle_table_row($key3,$value3)) .  "\n\t</li>\n</ul>" ;
							}
						}
						$second_row = true;
					}elseif($value->conceptProperties && $value2 != ''){
						 if($value2 != '') $parent_name = $value2;
						echo "\n\t<ul>\n\t" .self::xmle_table_row(($key != 'tty'?$key:$value2),($key != 'tty'?$value2:NULL)). "\n</ul>";
						}
				}
		}
		}
		unset($xml);
		unset($result);
		self::ob_cacher();
	}
	function stats($cache=false){
		return ($cache!=false?'<p><b><small>Rendering from cache</small></b></p>':NULL)."<p><b>Memory use: " . round(memory_get_usage() / 1024) . 'k'. "</b><p><b>Load time : "
	. sprintf("%.4f", (((float) array_sum(explode(' ',microtime())))-$this->start_time)) . " seconds</b></p><p><b>Overhead memory : ".$this->oh_memory." k</b></p>";

	}
}
echo '
<html>
	<head>
		<title>RxNorm Reference</title>
		<link rel="stylesheet" type="text/css" href="fixed_field.css" />
	</head>
	<body>
	<div id = "header">
		<h1>RxNorm Reference</h1>
	<form method="post" class="main_form">
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
	<form method="post" class="main_form">
		<fieldset>
			<legend>Lookup RXCUI</legend>
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
		</form>
	</div>

</ul>
<div id ="content">
';
 new RxNormRef;
