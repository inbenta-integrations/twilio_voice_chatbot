<?php

namespace Inbenta\TwilioConnector;

use Exception;
use Inbenta\ChatbotConnector\ChatbotConnector;
use Inbenta\ChatbotConnector\Utils\SessionManager;
use Inbenta\ChatbotConnector\ChatbotAPI\ChatbotAPIClient;
use Inbenta\TwilioConnector\ExternalAPI\TwilioAPIClient;
use Inbenta\TwilioConnector\ExternalDigester\TwilioDigester;
use Inbenta\TwilioConnector\HyperChatAPI\TwilioHyperChatClient;


class TwilioConnector extends ChatbotConnector
{
    private $messages = '';

    public function __construct($appPath)
    {
        // Initialize and configure specific components for Twilio
        try {
            parent::__construct($appPath);

            // Initialize base components
            parse_str(file_get_contents('php://input'), $request);

            $this->validateRequest($request);

            $conversationConf = [
                'configuration' => $this->conf->get('conversation.default'),
                'userType' => $this->conf->get('conversation.user_type'),
                'environment' => $this->environment,
                'source' => $this->conf->get('conversation.source')
            ];

            $this->session      = new SessionManager($this->getExternalIdFromRequest());
            $this->botClient    = new ChatbotAPIClient($this->conf->get('api.key'), $this->conf->get('api.secret'), $this->session, $conversationConf);

            // Try to get the translations from ExtraInfo and update the language manager
            $this->getTranslationsFromExtraInfo('twilio', 'translations');

            // Instance application components
            $externalClient        = new TwilioAPIClient(); // Instance Twilio client
            $chatClient            = new TwilioHyperChatClient($this->conf->get('chat.chat'), $this->lang, $this->session, $this->conf, $externalClient);  // Instance HyperchatClient for Twilio
            $externalDigester      = new TwilioDigester($this->lang, $this->conf->get('conversation.digester'), $this->session); // Instance Twilio digester

            $this->initComponents($externalClient, $chatClient, $externalDigester);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
            die();
        }
    }

    /**
     * Validate the incomming request
     */
    protected function validateRequest($request)
    {
        $twilio = $this->conf->get('twilio.credentials');
        if (
            !isset($twilio['account_sid']) || !isset($twilio['assistant_sid']) ||
            !isset($request['AccountSid']) || !isset($request['AssistantSid']) ||
            !isset($request['Channel']) ||
            $request['AccountSid'] !== $twilio['account_sid'] ||
            $request['AssistantSid'] !== $twilio['assistant_sid']
        ) {
            throw new Exception("Missing or wrong Twilio keys");
            die();
        }
    }

    /**
     * Return external id from request (Hyperchat of Twilio)
     */
    protected function getExternalIdFromRequest()
    {
        // Try to get user_id from a Twilio message request
        $externalId = TwilioAPIClient::buildExternalIdFromRequest();
        if (empty($externalId)) {
            throw new Exception("Invalid request");
            die();
        }
        return $externalId;
    }

    /**
     * Overwritten to handle the Twilio response
     */
    public function handleRequest()
    {
        try {
            $request = file_get_contents('php://input');
            // Translate the request into a ChatbotAPI request
            $externalRequest = $this->digester->digestToApi($request);
            // Check if it's needed to perform any action other than a standard user-bot interaction
            $nonBotResponse = $this->handleNonBotActions($externalRequest);
            if (!is_null($nonBotResponse)) {
                return $nonBotResponse;
            }
            // Handle standard bot actions
            $this->handleBotActions($externalRequest);
            // Send all messages
            return $this->externalClient->sendMessage($this->messages);
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }


    protected function sendMessagesToExternal($messages)
    {
        // Digest the bot response into the external service format
        $digestedBotResponse = $this->digester->digestFromApi($messages, $this->session->get('lastUserQuestion'));
        foreach ($digestedBotResponse as $message) {
            $this->messages .= ". " . $message;
        }
    }

    /**
     * Overwritten
     */
    protected function handleNonBotActions($digestedRequest)
    {
        // If user answered to an ask-to-escalate question, handle it
        if ($this->session->get('askingForEscalation', false)) {
            return $this->handleEscalation($digestedRequest);
        }
        return null;
    }

    /**
     * Overwritten
     */
    protected function handleEscalation($userAnswer = null)
    {
        $this->messages .=  ". " . $this->lang->translate('no_escalation_supported');
        $this->externalClient->sendTextMessage($this->messages);
    }

    /**
     *	Override useless function from parent
     */
    protected function returnOkResponse()
    {
        return true;
    }
}
