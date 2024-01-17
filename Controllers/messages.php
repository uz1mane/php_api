<?php

require_once "vendor.php";
require_once "functions.php";

function route($method, $urlData, $formData)
{
    switch ($method) {
        case 'GET':
            Get($urlData);
            break;
        case 'DELETE':
            Delete($urlData);
            break;
        default:
            http_response_code(501);
            break;
    }
}

function Get($urlData)
{
    $headers = getallheaders();
    global $connect;

    if (isset($headers["Authorization"])) {
        $token = str_replace("Bearer ", "", $headers["Authorization"]);

        $userId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

        if (isset($userId)) {
            switch (sizeof($urlData)) {
                case 0:
                    $request = "SELECT * FROM `message` WHERE SenderId=$userId OR RecieverId = $userId";
                    $messages = $connect->query($request);
                    PrintJson($messages);

                    break;
                case 1:
                    if (is_numeric($urlData[0])) {
                        $messageId = $urlData[0];
                        $request = "SELECT * FROM `message` WHERE Id = $messageId";
                        $message = $connect->query($request);
                        $fetched = mysqli_fetch_array($connect->query($request));

                        if ($fetched['SenderId'] == $userId or $fetched['RecieverId'] == $userId) {
                            PrintJson($message);

                        } else {
                            http_response_code(401);
                            exit();
                        }
                    } else {
                        http_response_code(501);
                    }
                    break;
                default:
                    http_response_code(501);
                    break;
            }
        }
    }
}

function Delete($urlData)
{
    $headers = getallheaders();
    global $connect;
    if (is_numeric($urlData[0])) {
        $messageId = $urlData[0];

        if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
        else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

        if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {
            $request = "DELETE FROM `message` WHERE Id = '$messageId'";
            $connect->query($request);
        } else if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
            $token = str_replace("Bearer ", "", $headers["Authorization"]);
            $userId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

            $message = mysqli_fetch_array($connect->query("SELECT * FROM `message` WHERE `message`.`Id` = '$messageId'"))[0];

            if ($userId == $message[SenderId] || $userId == $message[RecieverId]) {
                $request = "DELETE FROM `message` WHERE Id = '$messageId'";
                $connect->query($request);
            } else {
                http_response_code(401);
                exit();
            }
        } else {
            http_response_code(401);
            exit();
        }
    } else {
        http_response_code(501);
        exit();
    }
}

?>