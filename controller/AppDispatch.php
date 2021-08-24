<?php

/*
 * @package      OpenEMR
 * @link               https://www.open-emr.org
 *
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2021 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 *
 */

namespace OpenEMR\Modules\LifeMesh;

require_once "Container.php";
/**
 * Class AppDispatch
 * @package OpenEMR\Modules\LifeMesh
 */
class AppDispatch
{
    public $accountCheck;
    public $accountSummary;
    private $db;
    public $createSession;
    private $store;


    /**
     * AppDispatch constructor.
     */
    public function __construct()
    {
        $this->db = new Container();
        $this->store = $this->db->getDatabase();
    }

    /**
     * @param $username
     * @param $password
     * @param $url
     * @return string
     */
    public function apiRequest($username, $password, $url)
    {
        $data = base64_encode($username . ':' . $password);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->setUrl($url)); //dynamically set the url for the api request
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $data]);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $response = curl_exec($curl);

        curl_close($curl);

        if ($url == 'accountCheck') {
            if ($status === 0) {
                return true;
            } else {
                echo $status;
                die(" An Error occured. Username or Password is incorrect. Please contact Lifemesh ");
            }
        }
        if ($url == 'accountSummary') {
            return $response;
        }
    }

    public function apiRequestSession(
        $username,
        $password,
        $url,
        $callid,
        $eventid,
        $eventdatetimeutc,
        $eventdatetimelocal,
        $patientfirstname,
        $patientlastname,
        $patientemail,
        $patientcell
    )
    {
        $data = base64_encode($username . ':' . $password);
        $header = [
            'Authorization: Basic ' . $data,
            'Content-Type: application/json'
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->setUrl($url),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                                 "caller_id":"' . $callid . '",
                            "appointment_id":"' . $eventid . '",
                      "appointment_datetime":"' . $eventdatetimeutc . '",
                "appointment_datetime_local":"' . $eventdatetimelocal . '",
                        "patient_first_name":"' . $patientfirstname . '",
                         "patient_last_name":"' . $patientlastname . '",
                             "patient_email":"' . $patientemail . '",
                       "patient_cell_number":"' . $patientcell . '"
            }',
            CURLOPT_HTTPHEADER => $header
        ));
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $response = curl_exec($curl);
file_put_contents("/var/www/html/errors/status.txt", 'Status code is ' . $status);
        curl_close($curl);

        if ($status === 0) {
            $datatostore = json_decode($response, true);

                         $meetingid = $datatostore['MeetingID'];
                         $patient_code = $datatostore['PatientCode'];
                         $patient_uri = $datatostore['PatientURL'];
                         $provider_code = $datatostore['ProviderCode'];
                         $provider_uri = $datatostore['ProviderURL'];
                         $event_status = 'Scheduled';
                         $updatedAt = date("Y-m-d H:m:i");
file_put_contents("/var/www/html/errors/timesaved.txt", $eventdatetimelocal);
            $time = explode("T", $eventdatetimelocal);
            $this->store->saveSessionData(
                $eventid,
                $meetingid,
                $patient_code,
                $patient_uri,
                $provider_code,
                $provider_uri,
                $eventdatetimelocal,
                $time[1],
                $event_status,
                $updatedAt
            );
        } else {
            error_log('Lifemesh create session failed'. $status );
        }
    }

    public function rescheduleSession($username, $password, $callerid, $eventdatetime,$eventlocaltime, $eventid, $url)
    {
        $data = base64_encode($username . ':' . $password);
        $header = [
            'Authorization: Basic ' . $data,
            'Content-Type: application/json'
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->setUrl($url),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                    "caller_id":"' . $callerid . '",
                    "appointment_id":"' . $eventid . '",
                    "new_appointment_datetime":"' . $eventdatetime . '",
                    "New_appointment_datetime_local":"' . $eventlocaltime . '"
                }',
            CURLOPT_HTTPHEADER => $header
        ));
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $response = curl_exec($curl);
        if ($status != 0) {
            echo $response;
            die;
        }

        curl_close($curl);

    }
    /**
     * @param $value
     * @return string|null
     * set URL values based on the call to action
     */
    private function setUrl($value)
    {
        switch ($value) {
            case "accountCheck":
                return 'https://huzz90crca.execute-api.us-east-1.amazonaws.com/account_check';

            case "accountSummary":
                return 'https://huzz90crca.execute-api.us-east-1.amazonaws.com/account_summary';

            case "createSession":
                return 'https://huzz90crca.execute-api.us-east-1.amazonaws.com/create_session';

            case "rescheduleSession":
                return 'https://huzz90crca.execute-api.us-east-1.amazonaws.com/reschedule_session';

            default:
                return NULL;
        }
    }
}
