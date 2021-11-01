<?php

namespace Inbenta\TwilioConnector\ExternalDigester;

use \Exception;
use Inbenta\ChatbotConnector\ExternalDigester\Channels\DigesterInterface;
use Inbenta\TwilioConnector\Helpers\Helper;

class TwilioDigester extends DigesterInterface
{
    protected $conf;
    protected $channel;
    protected $langManager;
    protected $session;
    protected $attachableFormats = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'avi', 'mp3'];

    /**
     * Digester contructor
     */
    public function __construct($langManager, $conf, $session)
    {
        $this->langManager = $langManager;
        $this->channel = 'twilio';
        $this->conf = $conf;
        $this->session = $session;
    }

    /**
     *	Returns the name of the channel
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     **	Checks if a request belongs to the digester channel
     **/
    public static function checkRequest($_request)
    {
        parse_str($_request, $request);

        if (isset($request['CurrentInput'])) {
            return true;
        }

        return false;
    }

    /**
     * Formats a channel request into an Inbenta Chatbot API request
     * @param string $_request
     * @return array
     */
    public function digestToApi($_request)
    {
        parse_str($_request, $request);

        $userMessage = isset($request['CurrentInput']) ? $request['CurrentInput'] : '';

        $output = $this->checkOptions($userMessage);
        if (count($output) == 0 && trim($userMessage) !== '') {
            $output[0] = ['message' => $userMessage];
        } else if (count($output) == 0 && !$this->session->get('welcomeMessageShown', false)) {
            $output[0] = ['directCall' => 'sys-welcome'];
            $this->session->set('welcomeMessageShown', true);
        } else if (count($output) == 0) {
            //No message to bot
            //Launch the response for unknow request
            $output[0] = ['message' => ''];
        }
        return $output;
    }

    /**
     * Check if the response has options
     * @param string $userMessage
     * @return array $output
     */
    protected function checkOptions(string $userMessage)
    {
        $output = [];
        if ($this->session->has('options')) {
            $userMessage = Helper::removeFinalDots($userMessage);
            $lastUserQuestion = $this->session->get('lastUserQuestion');
            $options = $this->session->get('options');
            $this->session->delete('options');
            $this->session->delete('lastUserQuestion');

            $selectedOption = false;
            $selectedOptionText = "";
            $selectedEscalation = "";
            $isListValues = false;
            $isPolar = false;
            $isEscalation = false;
            $optionSelected = false;
            foreach ($options as $option) {
                if (isset($option->list_values)) {
                    $isListValues = true;
                } else if (isset($option->is_polar)) {
                    $isPolar = true;
                } else if (isset($option->escalate)) {
                    $isEscalation = true;
                }
                if (Helper::removeAccentsToLower($userMessage) === Helper::removeAccentsToLower($this->langManager->translate($option->label))) {
                    if ($isListValues) {
                        $selectedOptionText = $option->label;
                    } else if ($isEscalation) {
                        $selectedEscalation = $option->escalate;
                    } else {
                        $selectedOption = $option;
                        $lastUserQuestion = isset($option->title) && !$isPolar ? $option->title : $lastUserQuestion;
                    }
                    $optionSelected = true;
                    break;
                }
            }

            if (!$optionSelected) {
                if ($isListValues) { //Set again options for variable
                    if ($this->session->get('optionListValues', 0) < 1) { //Make sure only enters here just once
                        $this->session->set('options', $options);
                        $this->session->set('lastUserQuestion', $lastUserQuestion);
                        $this->session->set('optionListValues', 1);
                    } else {
                        $this->session->delete('options');
                        $this->session->delete('lastUserQuestion');
                        $this->session->delete('optionListValues');
                    }
                } else if ($isPolar) { //For polar, on wrong answer, goes for NO
                    $output[0]['message'] = $this->langManager->translate('no');
                } else if ($isEscalation) {
                    $output[0] = ['escalateOption' => false];
                }
            } else {
                if ($selectedOption) {
                    $output[0]['option'] = $selectedOption->value;
                } else if ($selectedOptionText !== "") {
                    $output[0]['message'] = $selectedOptionText;
                } else if ($isEscalation && $selectedEscalation !== "") {
                    $output[0] = ['escalateOption' => $selectedEscalation];
                }
            }
        }
        return $output;
    }

    /**
     * Formats an Inbenta Chatbot API response into a channel request
     * @param object $request
     * @param string $lastUserQuestion = ''
     */
    public function digestFromApi($request, $lastUserQuestion = '')
    {
        $messages = [];
        //Parse request messages
        if (isset($request->answers) && is_array($request->answers)) {
            $messages = $request->answers;
        } elseif (!is_null($this->checkApiMessageType($request))) {
            $messages = array('answers' => $request);
        }

        $output = [];
        foreach ($messages as $msg) {
            $msgType = $this->checkApiMessageType($msg);
            $digestedMessage = "";
            switch ($msgType) {
                case 'answer':
                    $digestedMessage = $this->digestFromApiAnswer($msg, $lastUserQuestion);
                    break;
                case 'polarQuestion':
                    $digestedMessage = $this->digestFromApiPolarQuestion($msg, $lastUserQuestion);
                    break;
                case 'multipleChoiceQuestion':
                    $digestedMessage = $this->digestFromApiMultipleChoiceQuestion($msg, $lastUserQuestion);
                    break;
                case 'extendedContentsAnswer':
                    $digestedMessage = $this->digestFromApiExtendedContentsAnswer($msg, $lastUserQuestion);
                    break;
            }
            if (trim($digestedMessage) !== '') {
                $output[] = $digestedMessage;
            }
        }
        return $output;
    }

    /**
     **	Classifies the API message into one of the defined $apiMessageTypes
     **/
    protected function checkApiMessageType($message)
    {
        $responseType = null;
        foreach ($this->apiMessageTypes as $type) {
            switch ($type) {
                case 'answer':
                    $responseType = $this->isApiAnswer($message) ? $type : null;
                    break;
                case 'polarQuestion':
                    $responseType = $this->isApiPolarQuestion($message) ? $type : null;
                    break;
                case 'multipleChoiceQuestion':
                    $responseType = $this->isApiMultipleChoiceQuestion($message) ? $type : null;
                    break;
                case 'extendedContentsAnswer':
                    $responseType = $this->isApiExtendedContentsAnswer($message) ? $type : null;
                    break;
            }
            if (!is_null($responseType)) {
                return $responseType;
            }
        }
        throw new Exception("Unknown ChatbotAPI response: " . json_encode($message, true));
    }

    /********************** API MESSAGE TYPE CHECKERS **********************/

    protected function isApiAnswer($message)
    {
        return isset($message->type) && $message->type == 'answer';
    }

    protected function isApiPolarQuestion($message)
    {
        return isset($message->type) && $message->type == "polarQuestion";
    }

    protected function isApiMultipleChoiceQuestion($message)
    {
        return isset($message->type) && $message->type == "multipleChoiceQuestion";
    }

    protected function isApiExtendedContentsAnswer($message)
    {
        return isset($message->type) && $message->type == "extendedContentsAnswer";
    }

    protected function hasTextMessage($message)
    {
        return isset($message->message) && is_string($message->message);
    }


    /********************** CHATBOT API MESSAGE DIGESTERS **********************/

    protected function digestFromApiAnswer($message, $lastUserQuestion)
    {
        $messageTxt = $message->message;

        if (isset($message->attributes->SIDEBUBBLE_TEXT) && !empty($message->attributes->SIDEBUBBLE_TEXT)) {
            $messageTxt .= "\n" . $message->attributes->SIDEBUBBLE_TEXT;
        }

        $this->handleMessageWithActionField($message, $messageTxt, $lastUserQuestion);
        $messageTxt = Helper::handleMessageWithLinks($messageTxt);

        // Add simple text-answer
        $output = Helper::cleanMessage($messageTxt);

        return $output;
    }


    protected function digestFromApiMultipleChoiceQuestion($message, $lastUserQuestion, $isPolar = false)
    {
        $output = Helper::cleanMessage($message->message);

        $options = $message->options;
        foreach ($options as &$option) {
            if (isset($option->attributes->title) && !$isPolar) {
                $option->title = $option->attributes->title;
            } elseif ($isPolar) {
                $option->is_polar = true;
            }
            $output .= ". " . $option->label;
        }
        $this->session->set('options', $options);
        $this->session->set('lastUserQuestion', $lastUserQuestion);

        return $output;
    }

    protected function digestFromApiPolarQuestion($message, $lastUserQuestion)
    {
        return $this->digestFromApiMultipleChoiceQuestion($message, $lastUserQuestion, true);
    }


    protected function digestFromApiExtendedContentsAnswer($message, $lastUserQuestion)
    {
        $output = Helper::cleanMessage($message->message);

        $messageTitle = [];
        $messageExtended = [];
        $hasUrl = false;

        foreach ($message->subAnswers as $index => $subAnswer) {

            $messageTitle[$index] = $subAnswer->message;

            if (!isset($messageExtended[$index])) $messageExtended[$index] = [];

            if (isset($subAnswer->parameters) && isset($subAnswer->parameters->contents)) {
                if (isset($subAnswer->parameters->contents->url)) {
                    $messageExtended[$index][] = " (" . $subAnswer->parameters->contents->url->value . "). ";
                    $hasUrl = true;
                }
            }
        }

        $messageTmp = "";
        if ($hasUrl) {
            foreach ($messageTitle as $index => $mt) {
                $messageTmp .= ". " . $mt;
                foreach ($messageExtended[$index] as $key => $me) {
                    $messageTmp .= ($key == 0 ? ". " : "") . $me;
                }
            }
        } else {
            if (count($messageTitle) == 1) {
                $messageTmp = ". " . $this->digestFromApiAnswer($message->subAnswers[0], $lastUserQuestion);
            } else if (count($messageTitle) > 1) {
                //$messageTmp = ". ";
                foreach ($messageTitle as $index => $mt) {
                    $messageTmp .= ". " . $mt;
                }
                $this->session->set('federatedSubanswers', $message->subAnswers);
            }
        }
        $output .= Helper::cleanMessage($messageTmp);

        return $output;
    }

    /********************** MISC **********************/

    /**
     * Create the content for ratings
     */
    public function buildContentRatingsMessage($ratingOptions, $rateCode)
    {
        return "";
    }

    /**
     * Validate if the message has action fields
     */
    private function handleMessageWithActionField($message, &$messageTxt, $lastUserQuestion)
    {
        if (isset($message->actionField) && !empty($message->actionField)) {
            if ($message->actionField->fieldType === 'list') {
                $options = $this->handleMessageWithListValues($message->actionField->listValues, $lastUserQuestion);
                if ($options !== "") {
                    $messageTxt .= $options;
                }
            }
        }
    }

    protected function handleMessageWithImages($message)
    {
        return '';
    }

    /**
     * Set the options for message with list values
     */
    protected function handleMessageWithListValues($listValues, $lastUserQuestion)
    {
        $optionList = "";
        $options = $listValues->values;
        foreach ($options as &$option) {
            $option->list_values = true;
            $option->label = $option->option;
            $optionList .= ". " . $option->label;
        }
        if ($optionList !== "") {
            $this->session->set('options', $options);
            $this->session->set('lastUserQuestion', $lastUserQuestion);
        }
        return $optionList;
    }

    /**
     *	Disabled for Twilio Voice
     */
    protected function buildUrlButtonMessage($message, $urlButton)
    {
        return [];
    }

    /**
     * Build the message and options to escalate
     * @return array
     */
    public function buildEscalationMessage()
    {
        $escalateOptions = [
            (object) [
                "label" => 'yes',
                "escalate" => true
            ],
            (object) [
                "label" => 'no',
                "escalate" => false
            ],
        ];

        $this->session->set('options', (object) $escalateOptions);
        $message = $this->langManager->translate('ask_to_escalate');
        $message .= ". " . $this->langManager->translate('yes');
        $message .= ". " . $this->langManager->translate('no');

        return $message;
    }
}
