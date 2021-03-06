<?php
/**
 * opera authentication script based on
 * pecl oauth extension
 */
session_start();
include_once("config.php");

if (array_key_exists('reset', $_REQUEST) && $_REQUEST['reset']) {
    unset($_SESSION['orequest_token_secret']);
    unset($_SESSION['oaccess_oauth_token']);
    unset($_SESSION['oaccess_oauth_token_secret']);
}

$err="Error [OAuth]: ";

try {
    if(!isset($oauth['opera']['consumerkey'])) {
        error_log($err."You must set the OAuth consumer key in the configuration file");
        exit;
    }

    if(!isset($oauth['opera']['consumersecret'])) {
        error_log($err."You must set the OAuth consumer secret in the configuration file");
        exit;
    }

    $oauthc = new OAuth($oauth['opera']['consumerkey'],
                        $oauth['opera']['consumersecret'],
                        OAUTH_SIG_METHOD_HMACSHA1,
                        OAUTH_AUTH_TYPE_URI); //initiate

    $oauthc->enableDebug();

    if(empty($_SESSION['orequest_token_secret'])) {
        // first stage is to get and keep the request token and secret
        // and then re-direct the user to page where they enter their
        // credentials for this request, we need to use POST method
        $request_token_url = $oauth['opera']['requesttokenurl'];
        $request_token_info = $oauthc->getRequestToken($request_token_url,
                                                       OAUTH_HTTP_METHOD_POST);

        // check for errors
        if($request_token_info == FALSE) {
            error_log($err."The OAuth server did not provide the request token and secret");
            exit;
        }

        // store the request token & secret for the access token stage
        $_SESSION['orequest_token_secret'] = $request_token_info['oauth_token_secret'];
        $_SESSION['orequest_token'] = $request_token_info['oauth_token'];

        // redirect user to the authorization (login) page
        header("Location: {$oauth['opera']['authurl']}?oauth_token=".$request_token_info['oauth_token']);//forward user to authorize url
    }
    else if(empty($_SESSION['oaccess_oauth_token'])) {
        // second stage is to request an access token
        // for which we have to provide the verifier
        // store the verifier from the request
        $_SESSION['ooauth_verifier'] = $_REQUEST['oauth_verifier'];

        // check for errors
        if(empty($_SESSION['ooauth_verifier'])) {
            error_log($err."The OAuth server did not provide the OAuth verifier in the callback");
            exit;
        }

        //get the access token - dont forget to save it
        $request_token_secret = $_SESSION['orequest_token_secret'];
        $request_token = $_SESSION['orequest_token'];
        $verifier = $_SESSION['ooauth_verifier'];

        //make the request. this time with GET method
        $oauthc->setToken($request_token,$request_token_secret);
        $access_token_info = $oauthc->getAccessToken($oauth['opera']['accesstokenurl'],'',$verifier);

        // check for errors
        if($access_token_info == FALSE) {
            error_log($err."The OAuth server did not provide the access token and secret");
            exit;
        }

        //store the access token so that we can use it when interacting with the API
        $_SESSION['oaccess_oauth_token']= $access_token_info['oauth_token'];
        $_SESSION['oaccess_oauth_token_secret']= $access_token_info['oauth_token_secret'];
    }
    if(isset($_SESSION['oaccess_oauth_token'])) {
        //now fetch current users speeddial data
        $access_token = $_SESSION['oaccess_oauth_token'];
        $access_token_secret =$_SESSION['oaccess_oauth_token_secret'];
        $oauthc->setToken($access_token,$access_token_secret);

        // make the request
        try {
            $data = $oauthc->fetch('https://link.api.opera.com/rest/speeddial/children/');
        }
        catch (OAuthException $e) {
            echo("The response information:");
            echo("<pre>");
            print_r($oauthc->getLastResponseInfo());
            echo("</pre>");
            echo("The response body:");
            echo("<pre>");
            print_r($oauthc->getLastResponse());
            echo("</pre>");
            echo("The PHP exception info:");
            echo("<pre>");
            echo($e);
            echo("</pre>");
        }
        $response_info = $oauthc->getLastResponse();

        // print out the speeddial data
        $json_data_array = json_decode($response_info,true);
        uasort($json_data_array, function ($a, $b) {
                $apos = $a["id"];
                $bpos = $b["id"];
                if ($apos == $bpos) return 0;
                return ($bpos > $apos) ? -1 : 1;
            });

        echo("<html>");
        echo("<head><title>Opera Link SpeedDial PHP demo</title>");
        echo("<link rel=\"stylesheet\" href=\"css/index.css\" type=\"text/css\">");
        echo("</head>");

        echo("<body>");
        echo("<div id=\"wrap\">");
        echo("<div><h2>Opera Link - Speeddials</h2><a href=\"/link/index.php\">Update</a></div>");
        echo("<div>");
        echo("<ol>");

        foreach ($json_data_array as $sd) {
            echo("<li>");
            echo("<a href=\"".$sd["properties"]["uri"]."\">");
            echo("<img class=\"logo\" src=\"http://www.opera.com/bitmaps/error/operaicon.png\" />");
            echo("<div>");
            echo($sd["properties"]["title"]);
            echo("</div>");
            echo("</a>");
            echo("</li>");
        }


        echo("</ol>");
        echo("</div>");
        echo("</div>");
        echo("</body>");
        echo("</html>");
    }
} catch(OAuthException $E) {
    error_log($err.$E);
}
?>
