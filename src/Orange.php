<?php

namespace zembrowski\SMS;

/**
 * Class Orange
 * @package zembrowski\phpsms-poland
 */
class Orange
{
    public $url = 'https://www.orange.pl/'; // orange.pl URL
    private $user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/601.4.4 (KHTML, like Gecko) Version/9.0.3 Safari/601.4.4';
    private $login_request_uri = 'zaloguj.phtml'; // login form request uri
    private $login_post_query_string = '?_DARGS=/ocp/gear/infoportal/portlets/login/login-box.jsp'; // login form POST query string
    private $send_request_uri = 'portal/map/map/message_box?mbox_edit=new&mbox_view=newsms'; // request uri of form for sending new messages
    private $send_post_request_uri = 'portal/map/map/message_box?_DARGS=/gear/mapmessagebox/smsform.jsp'; // action target for POST request of the sending new messages form
    private $messages_request_uri = 'portal/map/map/message_box?mbox_view=sentmessageslist'; // request uri of the sent messages list
    public $max_length = '640'; // max. length of one SMS message according to the sending new messages form

    /**
     * @var session
     * @var html
     * @var dynSess
     * @var token
     */
    private $session;
    private $html;
    private $dynSess;
    private $token;

    /**
     * Instantiates the Requests handler with Session support.
     */
    public function __construct()
    {
        $session = new \Requests_Session($this->url);
        $session->useragent = $this->user_agent;
        $this->session = $session;
        $response = $this->session->get($this->login_request_uri);

        $html = new \simple_html_dom();
        $this->html = $html;

        $this->dynSess = rand(0, 999999999999999999);
        // For now there is no crosscheck of the dynSess value with the hidden input value _dynSessConf of the login form, therefore the value can be generated randomly and one request fewer made
        //$element = $this->find($response->body, 'div.login-box form input[name=_dynSessConf]', 0);
        //$this->dynSess = $element->value;
    }

    /**
     * Login at orange.pl
     *
     * You have to be register at orange.pl
     * Head to: https://www.orange.pl/rejestracja.phtml
     *
     * @param string $login - login or the number assosciated with the service you use
     * @param string $password - password (pol. "Hasło")
     */
    public function login($login, $password)
    {
        // Referer header set only to act more like a browser
        $this->session->headers['Referer'] = $this->url . $this->login_request_uri;

        $this->session->data = array(
            '_dyncharset' => 'UTF-8',
            '_dynSessConf' => $this->dynSess,
            '/tp/core/profile/login/ProfileLoginFormHandler.loginErrorURL' => $this->url . $this->send_request_uri,
            '_D:/tp/core/profile/login/ProfileLoginFormHandler.loginErrorURL' => '',
            '/tp/core/profile/login/ProfileLoginFormHandler.loginSuccessURL' => '',
            '_D:/tp/core/profile/login/ProfileLoginFormHandler.loginSuccessURL' => '',
            '/tp/core/profile/login/ProfileLoginFormHandler.firstEnter' => 'true',
            '_D:/tp/core/profile/login/ProfileLoginFormHandler.firstEnter' => '',
            '/tp/core/profile/login/ProfileLoginFormHandler.value.login' => $login,
            '_D:/tp/core/profile/login/ProfileLoginFormHandler.value.login' => '',
            '/tp/core/profile/login/ProfileLoginFormHandler.value.password' => $password,
            '_D:/tp/core/profile/login/ProfileLoginFormHandler.value.password' => '',
            '/tp/core/profile/login/ProfileLoginFormHandler.rememberMe' => 'false',
            '_D:/tp/core/profile/login/ProfileLoginFormHandler.rememberMe' => '',
            '/tp/core/profile/login/ProfileLoginFormHandler.login' => 'Zaloguj się',
            '_D:/tp/core/profile/login/ProfileLoginFormHandler.login' => '',
            '_DARGS' => '/ocp/gear/infoportal/portlets/login/login-box.jsp'
        );

        $response = $this->session->post($this->login_request_uri . $this->login_post_query_string);
        //echo $response->body;
        $this->token = $this->token($response->body);

        return array('check' => $this->check($response->body, 'div.box-error p'), 'free' => $this->free($response->body));
    }

    /**
     * Retrieves the token from the webform
     *
     * @param string $content - content to be searched through
     * @return string - token
     */
    private function token($content)
    {
        $element = $this->find($content, 'div#box-smsform form input[name=/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.token]', 0);

        return $element->value;
    }

    /**
     * Send a SMS through the webform at $this->send_post_request_uri
     *
     * @param string $number - number of the recipient (9 digits without leading zero for national numbers e.g. 501234567; two leading zeros followed by prefix for international numbers e.g. 004912345678901; no spaces or special chars)
     * @param string $text - content of the SMS
     */
     // TODO: check number for validaty
    public function send($number, $text)
    {
        if (strlen($text) <= 0 || strlen($text) > $this->max_length) {
            throw new Exception('The message must be longer than 0 characters, but shorter than ' . $this->max_length . ' characters');
        }

        // TO FIX: follow_redirects is default: true in $options at Requests::request. Following redirects causes error "Too many redirects" with send method.
        // It would be awesome to retrieve the free SMS left or check the sent items if the message was sent from the response body instead of making another
        $this->session->options['follow_redirects'] = false;

        // Referer header set only to act more like a browser
        $this->session->headers['Referer'] = $this->url . $this->send_request_uri;

        $this->session->data = array(
            '_dyncharset' => 'UTF-8',
            '_dynSessConf' => $this->dynSess,
            '/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.type' => 'sms',
            '_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.type' => '',
            '/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.errorURL' => $this->url . $this->send_request_uri,
            '_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.errorURL' => '',
            '/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.successURL' => $this->url . $this->messages_request_uri,
            '_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.successURL' =>'',
            '/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.to' => $number,
            '_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.to' => '',
            '/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.body' => $text,
            '_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.body' => '',
            '/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.token' => $this->token,
            '_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.token' => '',
            'enabled' => true,
            '/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create.x' => rand(0,50),
            '/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create.y' => rand(0,25),
            '/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create' => 'Wyślij',
            '_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create' => '',
            '_DARGS' => '/gear/mapmessagebox/smsform.jsp'
        );

        $response = $this->session->post($this->send_post_request_uri);

        // TO FIX: Not working yet as expected: "Too many redirects" error; cf. follow_redirects option above
        //echo $response->body;
        return array('check' => $this->check($response->body, 'div.box-error p'), 'free' => $this->free($response->body));
    }

    /**
     * Find element in HTML Dom
     *
     * @param string $content - content to be searched through
     * @return object
     */
    private function find($content, $selector, $nth = null)
    {
        $this->html->load($content);
        if (is_int($nth) || $nth === 0) {
            $result = $this->html->find($selector, $nth);
        } else {
            $result = $this->html->find($selector);
        }

        return $result;
    }

    /**
     * Checks the remaining SMS left this month from the response body
     *
     * @param string $content - content to be searched through
     * @return boolean/int - free SMS this month; false if no content; int if value present
     */
    private function free($content)
    {
        if ($content) {
            $element = $this->find($content, '#syndication p.item span.value', 0);
            $result = intval(trim($element->plaintext));
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Checks the remaining SMS left this month making a GET request
     *
     * @return int - free SMS this month
     */
    public function get_free()
    {
        $response = $this->session->get($this->send_request_uri);
        $element = $this->find($response->body, '#syndication p.item span.value', 0);
        $result = intval(trim($element->plaintext));

        return $result;
    }

    /**
     * Check whether errors have been returned
     *
     * @param string $content - response body of a request
     * @return boolean - false if an element described by the selector exists
     */
    private function check($content, $selector)
    {
        $elements = $this->find($content, $selector);
        if (count($elements) > 0) {
            foreach ($elements as $element) {
                throw new Exception(trim($element->plaintext));
            }
            return false;
        } else {
            return true;
        }
    }
}