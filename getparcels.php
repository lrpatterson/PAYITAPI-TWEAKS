<?php

    REQUIRE("CONNECTION_STRING.php");
    REQUIRE("WRITE_BAD_ATTEMPT_TO_LASERFICHE.php");

    header('Content-Type: application/json');

    //LOOPS THROUGH IP BLACKLIST AND ENSURES THE IP IS NOT ON IT

/*     $stmt=$pdo->prepare("SELECT DISTINCT [ipaddress] FROM [WebApps].[Payit].[Auditlog] WHERE [ipaddress] = :ipaddress");
    $stmt->bindParam(':ipaddress', $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
    $ipListedOrNot = $stmt->fetchColumn();

    if($ipListedOrNot) {
        http_response_code(401);
        exit();
    } */

    //ENSURES METHOD IS GET, IF NOT, BLACKLIST IP

    $IP = $_SERVER["REMOTE_ADDR"];

    if($_SERVER["REQUEST_METHOD"] != "GET") {

        writeBadAttemptToBlacklist($IP, "INVALID REQUEST ATTEMPT ".$_SERVER["REQUEST_METHOD"], "GETPARCELS", "", "1");
        //Remove in Prod
        echo "INVALID REQUEST ATTEMPT ".$_SERVER["REQUEST_METHOD"];
        http_response_code(405);

        exit();

    }

    if (isset($_SERVER["HTTP_AUTHORIZATION"])) {

        $auth = $_SERVER["HTTP_AUTHORIZATION"];

        $stmt = $pdo->prepare('SELECT [authtoken] FROM [WebApps].[Payit].[Comm] WHERE [authtoken] = :authtoken');
        $stmt->bindParam(':authtoken', $auth);
        $stmt->execute();

        $correctAuthToken = $stmt->fetchColumn();
    }

    if($correctAuthToken) {

        $allowed_GET_array = array("parcel_number", "owner_name", "property_address_house_number", "property_address_street_name", "account_number");


        //ENSURES THAT THE URL IS CORRECT AND ONLY PARAMETERS ALLOWED ARE PASSED, IF NOT, BLACKLIST

        foreach ($_GET as $key => $value) {

            if(!in_array($key, $allowed_GET_array)) {

                $IP = $_SERVER['REMOTE_ADDR'];


                writeBadAttemptToBlacklist($IP, "INVALID PARAMETERS PASSED: ".$key, "GETPARCELS", "", "1");
                //Remove in Prod
                echo "INVALID PARAMETERS PASSED: ".$key;
                http_response_code(400);

                exit();
            }

        }

        $parcel_number = $_GET['parcel_number'];

        if($parcel_number != "") {

            if(!is_numeric($parcel_number)) {
                writeBadAttemptToBlacklist($IP, "NON NUMERIC PARCEL NUMBER PASSED|".$parcel_number, "GETPARCELS", "", "0");
                //Remove in Prod
                echo "NON NUMERIC PARCEL NUMBER PASSED|".$parcel_number;
                http_response_code(400);
                exit();
            }

            if(strlen($parcel_number) != 14) {
                writeBadAttemptToBlacklist($IP, "PARCEL NUMBER IS NOT 14 CHARACTERS IN LENGTH|".$parcel_number, "GETPARCELS", "", "0");
                //Remove in Prod
                echo "PARCEL NUMBER IS NOT 14 CHARACTERS IN LENGTH|".$parcel_number;
                http_response_code(400);
                exit();
            }

        }

        $url = "http://192.168.0.33/ITS/services/PayIT.aspx/GetParcels?parcel_number=".$parcel_number;

        $owner_name = $_GET["owner_name"];

        if($owner_name != "") {

            $quotes_in_owner_name = preg_match('/"/', $owner_name);

            if($quotes_in_owner_name == 1) {
                $owner_name = rawurlencode($owner_name);
            } else {
                writeBadAttemptToBlacklist($IP, "OWNER NAME PASSED WITHOUT QUOTATIONS", "GETPARCELS", "", "0");
                //Remove in Prod
                echo "OWNER NAME PASSED WITHOUT QUOTATIONS";
                http_response_code(400);
                exit();
            }
            
        }
        
        //Added Handler for Account Number
        $account_number = $_GET['account_number'];

        if($account_number != "") {

            if(!is_numeric($account_number)) {
                writeBadAttemptToBlacklist($IP, "NON NUMERIC ACCOUNT NUMBER PASSED|".$account_number, "GETPARCELS", "", "0");
                //Remove in Prod
                echo "NON NUMERIC ACCOUNT NUMBER PASSED|".$account_number;
                http_response_code(400);
                exit();
            }


        }


        $url = $url."&owner_name=".$owner_name;
        $url = $url."&property_address_house_number=".$property_address_house_number;
        $url = $url."&property_address_street_name=".$property_address_street_name;
        //Added Account Name
        $url = $url."&account_number=".$account_number;

        $headers = array('Content-Type: application/json');

        $handle = curl_init($url);

        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($handle);
        curl_close($handle);

        $json_decoded = json_decode($output);
        $json_decoded = $json_decoded->d;

        //Added Handler to return [] if not property is found
        if (json_decode($json_decoded)->message) {

            echo "[]";
        } else {
            echo $json_decoded;
        }

    } else {
        writeBadAttemptToBlacklist($IP, "INVALID TOKEN PASSED", "GETPARCELS", "", "1");
        //Remove when in prod
        echo "INVALID TOKEN PASSED";
        http_response_code(401);
        exit();
    }
    
?>