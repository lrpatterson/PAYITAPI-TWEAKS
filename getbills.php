<?php

    REQUIRE("CONNECTION_STRING.php");
    REQUIRE("WRITE_BAD_ATTEMPT_TO_LASERFICHE.php");

    header('Content-Type: application/json');

    function addLeadingZerosToBillNumber($billNumberForFormat) {

        $billNumberForFormatLength = strlen($billNumberForFormat);

        switch($billNumberForFormatLength) {

            case 0:
                $formattedBillNumber = "000000";
                break;

            case 1:
                $formattedBillNumber = "00000".$billNumberForFormat;
                break;

            case 2:
                $formattedBillNumber = "0000".$billNumberForFormat;
                break;
            //removed dup case 3
            case 3:
                $formattedBillNumber = "000".$billNumberForFormat;
                break;

            case 4:
                $formattedBillNumber = "00".$billNumberForFormat;
                break;

            case 5:
                $formattedBillNumber = "0".$billNumberForFormat;
                break;

            case 6:
                $formattedBillNumber = $billNumberForFormat;
                break;

        }

        return $formattedBillNumber;
    }

    $IP = $_SERVER['REMOTE_ADDR'];

    //CHECKS DB TO BE SURE IP ISN'T BLOCKLISTED

/*     $stmt=$pdo->prepare("SELECT DISTINCT [ipaddress] FROM [WebApps].[Payit].[Auditlog] WHERE [ipaddress] = :ipaddress");
    $stmt->bindParam(':ipaddress', $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
    $ipListedOrNot = $stmt->fetchColumn();

    if($ipListedOrNot) {
        http_response_code(401);
        exit();
    } */

    //ENSURES THAT THE REQUEST METHOD IS GET, IF NOT, BLACKLIST THE IP

    if($_SERVER["REQUEST_METHOD"] != "GET") {

        writeBadAttemptToBlacklist($IP, "INVALID REQUEST ATTEMPT ".$_SERVER["REQUEST_METHOD"], "GETBILLS", "", "1");
        //REMOVE ECHO IN PROD
        echo "INVALID VERB USED: ".$_SERVER["REQUEST_METHOD"];
        http_response_code(405);

        exit();

    }


    //ENSURES THAT THE AUTH TOKEN IS CORRECT, IF NOT, BLACKLIST AT THE END

    if (isset($_SERVER["HTTP_AUTHORIZATION"])) {

        $auth = $_SERVER["HTTP_AUTHORIZATION"];

        $stmt = $pdo->prepare('SELECT [authtoken] FROM [WebApps].[Payit].[Comm] WHERE [authtoken] = :authtoken');
        $stmt->bindParam(':authtoken', $auth);
        $stmt->execute();

        $correctAuthToken = $stmt->fetchColumn();

    }

    if($correctAuthToken) {
        //Added account_number to array
        $allowed_GET_array = array("parcel_number","tax_year","bill_number","account_number");

        $uniqID = uniqid();

        //ENSURES THAT THE URL IS CORRECT AND ONLY PARAMETERS ALLOWED ARE PASSED, IF NOT, BLACKLIST

        foreach ($_GET as $key => $value) {

            if(!in_array($key, $allowed_GET_array)) {

                writeBadAttemptToBlacklist($IP, "INVALID PARAMETERS PASSED: ".$key, "GETBILLS", "", "1");
                //REMOVE ECHO IN PROD
                echo "INVALID PARAMETERS PASSED: ".$key;
                http_response_code(400);

                exit();
            }

        }

        $response_array = array();

        $parcel_number = $_GET["parcel_number"];
        $tax_year = $_GET["tax_year"];
        $bill_number = $_GET["bill_number"];
        //Added account_number
        $account_number = $_GET["account_number"];
        //isset($parcel_number) && is_numeric($parcel_number) && strlen($parcel_number) == 14 Removed for Troubleshooting
        if(1==1) {

            $handle = curl_init();
        
            $url = "http://192.168.0.33/ITS/services/PayIt.aspx/GetBills?parcel_number=".$parcel_number."&tax_year=".$tax_year."&bill_number=".$bill_number."&account_number=".$account_number;
        
            curl_setopt_array($handle,
        
            array(
                CURLOPT_URL => $url,
        
                CURLOPT_HTTPHEADER => array(                                                                          
                    'Content-Type: application/json',                                                                       
                    'Content-Length: ' . strlen($data)
                ),
        
                CURLOPT_RETURNTRANSFER => true
            )
        
          );
        
            $output = curl_exec($handle);
            curl_close($handle);

            $json_decoded = json_decode($output);
            
            $json_response = $json_decoded->d;

            //Need to handle for no bills found
            $json_response = json_decode($json_response);
            


            //HANDLES PARCEL ONLY
            //This is for handling no bill found message
            
            if ($json_response->message) {

                $no_bill_message = [];
                echo "[]";
            } else {
                foreach ($json_response as $json_node) {
                
                
                    $bill_numbers_with_zeros = addLeadingZerosToBillNumber($json_node->bill_number);
    
                    array_push($response_array, ["parcel_number" => $json_node->parcel_number,
                                                    "bill_number" => $json_node->bill_number,
                                                    "formatted_bill_number" => $bill_numbers_with_zeros,
                                                    "formatted_bill_number_with_year" => $json_node->tax_year.$bill_numbers_with_zeros,
                                                    "tax_year" => $json_node->tax_year,
                                                    "due_date" => $json_node->due_date,
                                                    "total_amount_due" => $json_node->total_amount_due,
                                                    "total_amount_paid" => $json_node->total_amount_paid,
                                                    "tax_amount_due" => $json_node->tax_amount_due,
                                                    "interest_due" => $json_node->interest_due,
                                                    "discount_due" => $json_node->discount_due]);
                }
    
                echo json_encode($response_array);
            }
            

            

    } else {
        writeBadAttemptToBlacklist($IP, "INVALID PARCEL PASSED|".$parcel_number, "GETBILLS", "", "0");
        echo "INVALID PARCEL NUMBER GIVEN: ".$parcel_number;
    }

    } else {

        writeBadAttemptToBlacklist($IP, "INVALID TOKEN PASSED", "GETBILLS", "", "1");
        //REMOVE ECHO IN PROD
        echo "INVALID TOKEN PASSED";
        http_response_code(401);
        exit();
    }

?>