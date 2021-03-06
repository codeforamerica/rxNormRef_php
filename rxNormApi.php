<?php
// SOAP/REST ... implementing REST with SOAP style method selection. Enjoy! Only supports XML for now.
// use _apiHelper($query path) instead of _request  for quick json support.
class rxNormApi extends APIBaseClass{
// would like to some day implement this in SOAP...

        public static $api_url = 'http://rxnav.nlm.nih.gov/REST';


	public $normalElements = Array(
		'TTY'=>'Term Type',
		'IN'=>'Ingredient',
		'PIN'=>'Precise Ingredient',
		'MIN'=>'Multiple Ingriedents',
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
	public $sourceTypes = Array
        (
		'GS',
            	'MDDB',
		'MMSL',
	        'MMX',
	        'MSH',
	        'MTHFDA',
	        'MTHSPL',
	        'NDDF',
		'NDFRT',
	        'RXNORM',
	        'SNOMEDCT',
	        'VANDF',
        );

	public $idTypes = Array
	(
		'AMPID',
		'GCN_SEQNO',
		'GFC',
		'GPPC',
		'HIC_SEQN',
		'LISTING_SEQ_NO',
		'MESH',
		'MMSL_CODE',
		'NDC', 
		'NUI',
		'SNOMEDCT',
		'SPL_SET_ID',
		'UMLSCUI',
		'UNII_CODE',
		'VUID'
	);

	public $relaTypes = Array
	(
		'consists_of',
		'constitutes',
		'contained_in',
		'contains',
		'dose_form_of',
		'form_of',
		'has_dose_form',
		'has_form',
		'has_ingredient',
		'has_ingredients',
		'has_part',
		'has_precise_ingredient',
		'has_quantified_form',
		'has_tradename',
		'ingredient_of',
		'ingredients_of', 
		'inverse_isa',
		'isa',
		'part_of',
		'precise_ingredient_of',
		'quantified_form_of',
		'reformulated_to',
		'reformulation_of',
		'tradename_of');
        public function __construct($url=NULL)
        {
                $this->output_type = 'xml';
                parent::new_request(($url?$url:self::$api_url));
	}
	function discover_api(){
	// designed to run the built in api functions (if the exist) to get valid values for some api method calls
		$this->setOutputType('xml');

		$xml = new SimpleXMLElement($this->getIdTypes());
        //	$idTypes = $this->getIdTypes();
//		$idTypes = $this->getIdTypes();
//		$xml->simplexml_load_string($idTypes);

		$this->idTypes = self::xml_to_array($xml,'idTypeList','idName');
		$xml->simplexml_load_string($this->getRelaTypes());
	
//	        $relaTypes = new SimpleXMLElement($this->getRelaTypes());        
        	$this->relaTypes = self::xml_to_array($xml,'relationTypeList','relationType');

		$xml->simplexml_load_string($this->getSourceTypes());
//        	$sourceTypes = new SimpleXMLElement($this->getSourceTypes());
        	$this->sourceTypes = self::xml_to_array($xml,'sourceTypeList','sourceName');

	}

	function xml_to_array($xml_element,$list1,$list2){
// takes a three-dimensional array(SimpleXMLElement) (each only containing one element, except for the third dimension)
// reduces it to this third dimension and returns an array with its values.  We have to do
// some messiness because of the way SimpleXMLElement behaves (it is not an actual object so variable casting
// is sometimes required)
     		$xml_element = $xml_element->$list1;
        	foreach($xml_element->$list2 as $loc=>$item){
                	$xml_element .= $item .' ';
        	}
        	return array_filter(explode(' ',$xml_element));

	}

        public function setOutputType($type){
                if($type != $this->output_type)
                        $this->output_type = ($type != 'xml'?'json':'xml');
        }
        public function setRxcui($rxcui){
                $this->rxcui = $rxcui;
        }

        public function _request($path,$method,$data=NULL){
                if ($this->output_type == 'json')
                        return parent::_request($path,$method,$data,"Accept:application/json");
                else
                        return parent::_request($path,$method,$data,"Accept:application/xml");
        }

        public function getRxcui($rxcui=NULL){
                if($rxcui != NULL) return $rxcui;
                elseif($this->rxcui) return $this->rxcui;
        }
        public function findRxcuiByString( $name, $searchString =NULL,$searchType=NULL,$source_list=NULL, $allSourcesFlag=NULL)      
        {
                $data['name'] = $name;
                if($allSourcesFlag =! NULL ){
                        $data['allsrc'] = $allSourcesFlag;
                        if($source_list != NULL && $allSourcesFlag == 1) $data['srclist'] = $source_list;
                }
                if($searchString != NULL)
                        $data['search']=$searchString;
                return self::_request("/rxcui",'GET',$data);

        }
        public function findRxcuiByID($idType,$id,$allSourcesFlag=NULL){
                $data['idtype'] = $idType;
                $data['id'] = $id;
                if($allSourcesFlag != NULL ) $data['allsrc'] = $allSourcesFlag;
                return self::_request("/rxcui?idtype=$idType&id=$id&allsrc=$allSourcesFlag",'GET');
        }

        public function getSpellingSuggestions( $searchString ){
                return self::_request("/spellingsuggestions?name=$searchString",'GET');
        }

        public function getRxConceptProperties( $rxcui = NULL ){
                $rxcui = self::getRxcui($rxcui);
                return self::_request('/rxcui/'.$rxcui.'/properties','GET');
        }
        public function getRelatedByRelationship( $relationship_list , $rxcui = NULL ){
                $rxcui = self::getRxcui($rxcui);
                return self::_request("/rxcui/$rxcui/related?rela=$relationship_list",'GET');
        }
        public function getRelatedByType($type_list, $rxcui = NULL ){
                $rxcui = self::getRxcui($rxcui);
                return self::_request("/rxcui/$rxcui/related?tty=$type_list",'GET');
        }
        public function getAllRelatedInfo( $rxcui = NULL ){
                $rxcui = self::getRxcui($rxcui);
                return self::_request("/rxcui/$rxcui/allrelated",'GET');
        }
        public function getDrugs( $name ){
                return self::_request("/drugs?name=$name",'GET');
        }

        public function getNDCs( $rxcui = NULL){
                $rxcui = self::getRxcui($rxcui);
                return self::_request("/rxcui/$rxcui/ndcs",'GET');
        }

        public function getRxNormVersion(){
                return self::_request('/version','GET');
        }

        public function getIdTypes(){
                return self::_request('/idtypes','GET');
        }

        public function getRelaTypes(){
                return self::_request('/relatypes','GET');
        }

        public function getSourceTypes(){
                return self::_request("/sourcetypes",'GET');
        }

        public function getTermTypes(){
                return self::_request("/termtypes",'GET');
        }

        public function getProprietaryInformation($proxyTicket,$source_list=NULL,$rxcui=NULL ){
                $rxcui = self::getRxcui($rxcui);
                $data['ticket']= $proxyTicket;
                if($source_list != NULL) $data['srclist'] = $source_list;
                return self::_request("/rxcui/$rxcui/proprietary",'GET',$data);
        }

        public function getMultiIngredBrand( $rxcui_list ){
                return self::_request("/brands?ingredientids=$rxcui_list",'GET');
        }
        // assume this is get display names too ??
        public function getDisplayTerms(){
                return self::_request("/displaynames",'GET');
        }

        public function getStrength( $rxcui = NULL ){
                $rxcui = self::getRxcui($rxcui);
                return self::_request("/rxcui/$rxcui/strength",'GET');
        }

        public function getQuantity( $rxcui = NULL ){
                $rxcui = self::getRxcui($rxcui);
                return self::_request("/rxcui/$rxcui/quantity",'GET');
        }

        public function getUNII( $rxcui = NULL ){
                $rxcui = self::getRxcui($rxcui);
                return self::_request("/rxcui/$rxcui/unii",'GET');
        }

        public function getSplSetId( $idType,$id,$allsrc=NULL ){

                $data['idtype'] = $idType;
                $data['id'] = $id;
                if($allsrc != NULL) $data['allsrc']=$allsrc;
                return self::_request("/rxcui",'GET',$data);
        }

        public function findRemapped( $rxcui = NULL ){
                return self::_request("/remap/$rxcui",'GET');
        }

}
