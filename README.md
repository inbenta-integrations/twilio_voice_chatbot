# Twilio Voice Chatbot Template

### OBJECTIVE

This template has been implemented in order to conect the Inbenta Chatbot API and the **Twilio Voice**, with the minimum configuration and effort. The main library of this template is Twilio Voice Connector, which extends from a base library named [Chatbot API Connector](https://github.com/inbenta-integrations/chatbot_api_connector), built to be used as a base for different external services like Facebook, Skype, Line, etc.

This template includes **/conf** and **/lang** folders, which have all the configuration and translations required by the libraries, and a small file **server.php** which creates an TwilioConnector’s instance in order to handle all the incoming requests.

### FUNCTIONALITIES

This bot template inherits the functionalities from the `ChatbotConnector` library. Currently, the features provided by this application are:

- Simple answers
- Multiple options.
- Polar questions.
- Chained answers.
- Send information to webhook through forms.

> **NOTE:** Keep in mind we are using voice as a channel. So, not all variable types work best with voice. Example: Email, Date.

### INSTALLATION

It's pretty simple to get this UI working. The mandatory configuration files are included by default in `/conf/custom` to be filled in, so you have to provide the information required in these files:

- **File 'api.php'**
  Provide the API Key and API Secret of your Chatbot Instance.

- **File 'twilio.php'**
  Provide the Credentials (Account SID & Assistant SID).

- **File 'environments.php'**
  Here you can define regexes to detect `development` environment. If the regexes do not match the current conditions or there isn't any regex configured, `production` environment will be assumed.

### HOW TO CUSTOMIZE

**From configuration**

For a default behavior, the only requisite is to fill the basic configuration (more information in `/conf/README.md`). There are some extra configuration parameters in the configuration files that allow you to modify the basic-behavior.

**Custom Behaviors**

If you need to customize the bot flow, you need to extend the class `TwilioConnector`, included in the `/lib/TwilioConnector` folder. You can modify 'TwilioConnector' methods and override all the parent methods from `ChatbotConnector`.

### DEPENDENCIES

This application imports `inbenta/chatbot-api-connector` as a Composer dependency, that includes `symfony/http-foundation@^3.1` and `guzzlehttp/guzzle@~6.0` as dependencies too.

### To enable all capabilities, you need:

- Use the ANSWER_TEXT as text only
- Create a setting "CONVERSATION_ENDED"
- Farewell content:
  - Must be matched when the user says something to end the conversation
  - Add a directCall with the text "sys-goodbye"
  - Add "Learn with semantic expansion" with the variations to end a call (like "goodbye", "end call", etc).
