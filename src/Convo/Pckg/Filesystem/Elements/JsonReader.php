<?php declare(strict_types=1);

namespace Convo\Pckg\Filesystem\Elements;

/**
 * Class JsonReader
 * @package Convo\Pckg\Core\Elements
 */
class JsonReader extends \Convo\Core\Workflow\AbstractWorkflowComponent implements \Convo\Core\Workflow\IConversationElement
{
    private $_scopeType		=	\Convo\Core\Params\IServiceParamsScope::SCOPE_TYPE_SESSION;
    private $_url;
    private $_var;
    private $_decode;

    public function __construct($properties) {

        parent::__construct( $properties);
        $this->_url     =   $properties['url'];
        $this->_var     =   $properties['var'];

        $this->_decode  =   isset( $properties['decode']) ? $properties['decode'] : false;

        if ( isset( $properties['scope_type'])) {
            $this->_scopeType	=	$properties['scope_type'];
        }
    }

    public function read( \Convo\Core\Workflow\IConvoRequest $request, \Convo\Core\Workflow\IConvoResponse $response)
    {
        $this->_logger->debug( 'Raw json path ['.$this->_url.']');
        $this->_logger->debug( 'Raw var ['.$this->_var.']');

        $url	=   $this->evaluateString( $this->_url);
        $var	=   $this->evaluateString( $this->_var);
        $scope_type     =   $this->evaluateString( $this->_scopeType);

        $this->_logger->debug( 'Using json path ['.$url.']');
        $this->_logger->debug( 'var to store ['.$var.']');

        $json_file = file_get_contents($url);
        $json = json_decode($json_file, true);

        if (($json != $json_file) && $json)
        {
            $this->_logger->debug('JSON is valid');

			if (is_bool($this->evaluateString($this->_decode))) {
				$decode = $this->evaluateString($this->_decode);
			} else {
				$decode = strpos($this->_decode, '${') !== false ? $this->evaluateString($this->_decode) : $this->_decode;
			}

            if($decode) {
                $json  =   \Convo\Core\Util\ArrayUtil::arrayWalk( $json, function ( $val) {
                    if ( is_string( $val)) {
                        $val    =   html_entity_decode( $val, ENT_QUOTES);
                        return htmlspecialchars_decode( $val);
                    }
                    return $val;
                });
            }
        }
        else
        {
            $this->_logger->debug('JSON is invalid');
        }

        $params = $this->getService()->getServiceParams( $scope_type);
        $params->setServiceParam( $var, $json);
    }


    // UTIL
    public function __toString()
    {
        return parent::__toString().'['.$this->_url.']';
    }

}

?>
