<?php

namespace Inbenta\TwilioConnector\HyperChatAPI;

use Inbenta\ChatbotConnector\HyperChatAPI\HyperChatClient;
use Inbenta\TwilioConnector\ExternalAPI\TwilioAPIClient;

class TwilioHyperChatClient extends HyperChatClient
{
    private $eventHandlers = array();
    private $session;
    private $appConf;
    private $externalId;

    function __construct($config, $lang, $session, $appConf, $externalClient)
    {
        // CUSTOM added session attribute to clear it
        $this->session = $session;
        $this->appConf = $appConf;
        parent::__construct($config, $lang, $session, $appConf, $externalClient);
    }

    //Instances an external client
    protected function instanceExternalClient($externalId, $appConf)
    {
        $userNumber = TwilioAPIClient::getUserNumberFromExternalId($externalId);
        if (is_null($userNumber)) {
            return null;
        }
        $companyNumber = TwilioAPIClient::getCompanyNumberFromExternalId($externalId);
        if (is_null($companyNumber)) {
            return null;
        }
        $externalClient = new TwilioAPIClient();

        $externalClient->setSenderFromId($companyNumber, $userNumber);
        $this->externalId = $externalClient->getExternalId();

        return $externalClient;
    }

    public static function buildExternalIdFromRequest($config)
    {
        $request = json_decode(file_get_contents('php://input'), true);

        $externalId = null;
        if (isset($request['trigger'])) {
            //Obtain user external id from the chat event
            $externalId = self::getExternalIdFromEvent($config, $request);
        }
        return $externalId;
    }
}
