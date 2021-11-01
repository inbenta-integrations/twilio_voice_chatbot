### OBJECTIVE

This Twilio connector extends from the [Chatbot API Connector](https://github.com/inbenta-integrations/chatbot_api_connector) library. It translates Twilio Voice messages into the Inbenta Chatbot API format and vice versa. Also, it implements some methods to make an escalation to a Live agent, using Twilio Functions.

### FUNCTIONALITIES
This connector inherits the functionalities from the `ChatbotConnector` library. Currently, the features provided by this application are:

* Simple answers
* Multiple options
* Polar questions
* Chained answers
* Escalate to Livechat after a number of no-results answers, using Twilio Functions (transfer call)
* Send information to webhook through forms

### HOW TO CUSTOMIZE

**Custom Behaviors**

If you need to customize the bot flow, you need to modify the class `TwilioConnector.php`. This class extends from the ChatbotConnector and here you can override all the parent methods.


### STRUCTURE

The `TwilioConnector` folder has some classes needed to use the ChatbotConnector with Twilio. These classes are used in the TwilioConnector constructor in order to provide the application with the components needed to send information to Twilio and to parse messages between Twilio Voice and ChatbotAPI.


**External Digester folder**

This folder contains the class TwilioDigester. This class is a kind of "translator" between the Chatbot API and Twilio Voice. Mainly, the work performed by this class is to convert a message from the Chatbot API into a message accepted by the Twilio Voice. It also does the inverse work, translating messages from Twilio into the format required by the Chatbot API.
