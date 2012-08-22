<?php  // $Id: searchlib.php,v 1.8 2006/09/26 05:08:39 martinlanghoff Exp $
global $CFG;
require_once($CFG->libdir.'/lexer.php');

// Constants for the various types of tokens

define("TOKEN_USER","0");
define("TOKEN_META","1");
define("TOKEN_EXACT","2");
define("TOKEN_NEGATE","3");
define("TOKEN_STRING","4");
define("TOKEN_USERID","5");
define("TOKEN_DATEFROM","6");
define("TOKEN_DATETO","7");
define("TOKEN_INSTANCE","8");

// Class to hold token/value pairs after they're parsed.

class search_token {
  var $value;
  var $type;
  function search_token($type,$value){
    $this->type = $type;
    $this->value = $this->sanitize($value);

  }

  // Try to clean up user input to avoid potential security issues.
  // Need to think about this some more.

  function sanitize($userstring){
    return htmlspecialchars($userstring);
  }
  function getValue(){
    return $this->value;
  }
  function getType(){
    return $this->type;
  }
}



// This class does the heavy lifting of lexing the search string into tokens.
// Using a full-blown lexer is probably overkill for this application, but
// might be useful for other tasks.

class search_lexer extends Lexer{

  function search_lexer(&$parser){

    // Call parent constructor.
    $this->Lexer($parser);

    //Set up the state machine and pattern matches for transitions.

    // Patterns to handle strings  of the form datefrom:foo

    // If we see the string datefrom: while in the base accept state, start
    // parsing a username and go to the indatefrom state.
    $this->addEntryPattern("datefrom:\S+","accept","indatefrom");

    // Snarf everything into the username until we see whitespace, then exit
    // back to the base accept state.
    $this->addExitPattern("\s","indatefrom");


    // Patterns to handle strings  of the form dateto:foo

    // If we see the string dateto: while in the base accept state, start
    // parsing a username and go to the indateto state.
    $this->addEntryPattern("dateto:\S+","accept","indateto");

    // Snarf everything into the username until we see whitespace, then exit
    // back to the base accept state.
    $this->addExitPattern("\s","indateto");


    // Patterns to handle strings  of the form instance:foo

    // If we see the string instance: while in the base accept state, start
    // parsing for instance number and go to the ininstance state.
    $this->addEntryPattern("instance:\S+","accept","ininstance");

    // Snarf everything into the username until we see whitespace, then exit
    // back to the base accept state.
    $this->addExitPattern("\s","ininstance");


    // Patterns to handle strings  of the form userid:foo

    // If we see the string userid: while in the base accept state, start
    // parsing a username and go to the inuserid state.
    $this->addEntryPattern("userid:\S+","accept","inuserid");

    // Snarf everything into the username until we see whitespace, then exit
    // back to the base accept state.
    $this->addExitPattern("\s","inuserid");


    // Patterns to handle strings  of the form user:foo

    // If we see the string user: while in the base accept state, start
    // parsing a username and go to the inusername state.
    $this->addEntryPattern("user:\S+","accept","inusername");

    // Snarf everything into the username until we see whitespace, then exit
    // back to the base accept state.
    $this->addExitPattern("\s","inusername");


    // Patterns to handle strings  of the form meta:foo

   // If we see the string meta: while in the base accept state, start
    // parsing a username and go to the inmeta state.
    $this->addEntryPattern("subject:\S+","accept","inmeta");

    // Snarf everything into the meta token until we see whitespace, then exit
    // back to the base accept state.
    $this->addExitPattern("\s","inmeta");


    // Patterns to handle required exact match strings (+foo) .

    // If we see a + sign  while in the base accept state, start
    // parsing an exact match string and enter the inrequired state
    $this->addEntryPattern("\+\S+","accept","inrequired");
    // When we see white space, exit back to accept state.
    $this->addExitPattern("\s","inrequired");

    // Handle excluded strings (-foo)

   // If we see a - sign  while in the base accept state, start
    // parsing an excluded string and enter the inexcluded state
    $this->addEntryPattern("\-\S+","accept","inexcluded");
    // When we see white space, exit back to accept state.
    $this->addExitPattern("\s","inexcluded");


    // Patterns to handle quoted strings.

    // If we see a quote  while in the base accept state, start
    // parsing a quoted string and enter the inquotedstring state.
    // Grab everything until we see the closing quote.

    $this->addEntryPattern("\"[^\"]+","accept","inquotedstring");

    // When we see a closing quote, reenter the base accept state.
    $this->addExitPattern("\"","inquotedstring");

    // Patterns to handle ordinary, nonquoted words.

    // When we see non-whitespace, snarf everything into the nonquoted word
    // until we see whitespace again.
    $this->addEntryPattern("\S+","accept","plainstring");

    // Once we see whitespace, reenter the base accept state.
    $this->addExitPattern("\s","plainstring");

  }
}




// This class takes care of sticking the proper token type/value pairs into
// the parsed token  array.
// Most functions in this class should only be called by the lexer, the
// one exception being getParseArray() which returns the result.

class search_parser {
    var $tokens;


    // This function is called by the code that's interested in the result of the parse operation.
    function get_parsed_array(){
        return $this->tokens;
    }

    /*
     * Functions below this are part of the state machine for the parse
     * operation and should not be called directly.
     */

    // Base state. No output emitted.
    function accept() {
        return true;
    }

    // State for handling datefrom:foo constructs. Potentially emits a token.
    function indatefrom($content){
        if (strlen($content) < 10) { // State exit or missing parameter.
            return true;
        }
        // Strip off the datefrom: part and add the reminder to the parsed token array
        $param = trim(substr($content,9));
        $this->tokens[] = new search_token(TOKEN_DATEFROM,$param);
        return true;
    }

    // State for handling dateto:foo constructs. Potentially emits a token.
    function indateto($content){
        if (strlen($content) < 8) { // State exit or missing parameter.
            return true;
        }
        // Strip off the dateto: part and add the reminder to the parsed token array
        $param = trim(substr($content,7));
        $this->tokens[] = new search_token(TOKEN_DATETO,$param);
        return true;
    }

    // State for handling instance:foo constructs. Potentially emits a token.
    function ininstance($content){
        if (strlen($content) < 10) { // State exit or missing parameter.
            return true;
        }
        // Strip off the instance: part and add the reminder to the parsed token array
        $param = trim(substr($content,9));
        $this->tokens[] = new search_token(TOKEN_INSTANCE,$param);
        return true;
    }


    // State for handling userid:foo constructs. Potentially emits a token.
    function inuserid($content){
        if (strlen($content) < 8) { // State exit or missing parameter.
            return true;
        }
        // Strip off the userid: part and add the reminder to the parsed token array
        $param = trim(substr($content,7));
        $this->tokens[] = new search_token(TOKEN_USERID,$param);
        return true;
    }


    // State for handling user:foo constructs. Potentially emits a token.
    function inusername($content){
        if (strlen($content) < 6) { // State exit or missing parameter.
            return true;
        }
        // Strip off the user: part and add the reminder to the parsed token array
        $param = trim(substr($content,5));
        $this->tokens[] = new search_token(TOKEN_USER,$param);
        return true;
    }


    // State for handling meta:foo constructs. Potentially emits a token.
    function inmeta($content){
        if (strlen($content) < 9) { // Missing parameter.
            return true;
        }
        // Strip off the meta: part and add the reminder to the parsed token array.
        $param = trim(substr($content,8));
        $this->tokens[] = new search_token(TOKEN_META,$param);
        return true;
    }


    // State entered when we've seen a required string (+foo). Potentially
    // emits a token.
    function inrequired($content){
        if (strlen($content) < 2) { // State exit or missing parameter, don't emit.
            return true;
        }
        // Strip off the + sign and add the reminder to the parsed token array.
        $this->tokens[] = new search_token(TOKEN_EXACT,substr($content,1));
        return true;
    }

    // State entered when we've seen an excluded string (-foo). Potentially
    // emits a token.
    function inexcluded($content){
        if (strlen($content) < 2) { // State exit or missing parameter.
            return true;
        }
        // Strip off the -sign and add the reminder to the parsed token array.
        $this->tokens[] = new search_token(TOKEN_NEGATE,substr($content,1));
        return true;
    }


    // State entered when we've seen a quoted string. Potentially emits a token.
    function inquotedstring($content){
        if (strlen($content) < 2) { // State exit or missing parameter.
            return true;
        }
        // Strip off the opening quote and add the reminder to the parsed token array.
        $this->tokens[] = new search_token(TOKEN_STRING,substr($content,1));
        return true;
    }

    // State entered when we've seen an ordinary, non-quoted word. Potentially
    // emits a token.
    function plainstring($content){
        if (ctype_space($content)) { // State exit
            return true;
        }
        // Add the string to the parsed token array.
        $this->tokens[] = new search_token(TOKEN_STRING,$content);
        return true;
    }
}


// Primitive function to generate a SQL string from a parse tree.
// Parameters:
//
// $parsetree should be a parse tree generated by a
// search_lexer/search_parser combination.
// Other fields are database table names to search.

function search_generate_SQL($parsetree, $datafield, $metafield, $mainidfield, $useridfield,
                             $userfirstnamefield, $userlastnamefield, $timefield, $instancefield) {
    global $CFG,$DB;

    if ($CFG->dbtype == "pgsql") {
        $REGEXP = "~*"; //case insensitive
        $LIKE = " ILIKE ";
    } else {
        $REGEXP = "REGEXP";
        $LIKE = " LIKE ";
    }

    $ntokens = count($parsetree);
    if ($ntokens == 0) {
        return "";
    }

    $SQLString = '';

    for ($i=0; $i<$ntokens; $i++){
        if ($i > 0) {// We have more than one clause, need to tack on AND
            $SQLString .= ' AND ';
        }

        $type = $parsetree[$i]->getType();
        $value = $parsetree[$i]->getValue();

        switch($type){
            case TOKEN_STRING:

                if ($datafield) {
                	$SQLString .= "(($datafield $LIKE '%$value%') OR ($metafield $LIKE '%$value%') )";
                } else {
                	$SQLString .= "($metafield $LIKE '%$value%')";
                }
                break;
            case TOKEN_EXACT:

            	if ($datafield) {
                	$SQLString .= "(($datafield $REGEXP '[[:<:]]".$value."[[:>:]]') OR ($metafield $REGEXP '[[:<:]]".$value."[[:>:]]'))";
            	} else {
            		$SQLString .= "($metafield $REGEXP '[[:<:]]".$value."[[:>:]]')";
            	}
                break;
            case TOKEN_META:
                if ($metafield != '') {
                    $SQLString .= "($metafield $LIKE '%$value%')";
                }
                break;
            case TOKEN_USER:
                $SQLString .= "(($mainidfield = $useridfield) AND (($userfirstnamefield $LIKE '%$value%') OR ($userlastnamefield $LIKE '%$value%')))";
                break;
            case TOKEN_USERID:
                $SQLString .= "($useridfield = $value)";
                break;
            case TOKEN_INSTANCE:
                $SQLString .= "($instancefield = $value)";
                break;
            case TOKEN_DATETO:
                $SQLString .= "($timefield <= $value)";
                break;
            case TOKEN_DATEFROM:
                $SQLString .= "($timefield >= $value)";
                break;
            case TOKEN_NEGATE:
                $SQLString .= "(NOT (($datafield  $LIKE '%$value%') OR ($metafield  $LIKE '%$value%')))";
                break;
            default:
                return '';

        }
    }
    return $SQLString;
}


?>
