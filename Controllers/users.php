<?php

require_once "vendor.php";
require_once "functions.php";

function route($method, $urlData, $formData)
{
    switch ($method) {
        case 'GET':
            Get($urlData, $formData);
            break;
        case 'POST':
            Post($urlData, $formData);
            break;
        case 'PATCH':
            Patch($urlData, $formData);
            break;
        case 'DELETE':
            Delete($urlData);
            break;
        default:
            http_response_code(501);
            break;
    }
}


function Get($urlData, $formData)
{
    switch (sizeof($urlData)) {
        case 0:
            GetAllUsers();
            break;
        case 1:
            if (is_numeric($urlData[0])) {
                GetUser($urlData);
            } else {
                http_response_code(501);
            }
            break;
        case 2:
            if ($urlData[0] == "photos") {
                GetSelectedUserPhotos($urlData[1]);
            }
            if ($urlData[1] == "posts") {
                GetUserPosts($urlData[0]);
            }
            if ($urlData[1] == "messages") {
                GetUserMessages($urlData[0], isset($formData["offset"]) ? $formData["offset"] : 0, isset($formData["limit"]) ? $formData["limit"] : 0);
            }
            break;
        default:
            http_response_code(501);
            break;
    }
}

function GetUserMessages($userId, $offset = 0, $limit = 0)
{
    $headers = getallheaders();
    global $connect;

    $limit = $limit == 0 ? 100 : $limit;

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {
        $request = "SELECT * FROM `message` WHERE SenderId=$userId OR RecieverId = $userId LIMIT $limit OFFSET $offset";
        PrintJson($connect->query($request));

    } else if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
        $token = str_replace("Bearer ", "", $headers["Authorization"]);
        $currentUserId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

        if ($userId == $currentUserId) {
            $request = "SELECT * FROM `message` WHERE SenderId=$userId OR RecieverId = $userId LIMIT $limit OFFSET $offset";
            PrintJson($connect->query($request));
        } else {
            http_response_code(401);
            exit();
        }
    } else {
        http_response_code(401);
        exit();
    }
}

function GetAllUsers()
{
    $headers = getallheaders();
    global $connect;

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {
        $request = "SELECT `user`.Id, `user`.Name, Surname, Username, Birthday, Avatar, Status, CASE WHEN `user`.`CityId` IS NOT NULL THEN `city`.Name END as City, `role`.Name as Role FROM user JOIN role ON `role`.Id = `user`.RoleId LEFT JOIN city ON `city`.Id = `user`.CityId";
        $users = $connect->query($request);
        PrintJson($users);
    } else {
        $request = "SELECT `user`.Id, `user`.Name, Surname, Username, Avatar, Status, CASE WHEN `user`.`CityId` IS NOT NULL THEN `city`.Name END as City FROM user LEFT JOIN city ON `city`.Id = `user`.CityId";
        $users = $connect->query($request);
        PrintJson($users);
    }
}

function GetUser($urlData)
{
    $userId = $urlData[0];

    $headers = getallheaders();
    global $connect;

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {
        $request = "SELECT `user`.Id, `user`.Name, Surname, Username, Birthday, Avatar, Status, CASE WHEN `user`.`CityId` IS NOT NULL THEN `city`.Name END as City, `role`.Name as Role FROM user JOIN role ON `role`.Id = `user`.RoleId LEFT JOIN city ON `city`.Id = `user`.CityId WHERE `user`.Id = $userId";
        $users = $connect->query($request);
        PrintJson($users);
    } else {
        $request = "SELECT `user`.Id, `user`.Name, Surname, Username, Avatar, Status, CASE WHEN `user`.`CityId` IS NOT NULL THEN `city`.Name END as City FROM user LEFT JOIN city ON `city`.Id = `user`.CityId WHERE `user`.Id = $userId";
        $users = $connect->query($request);
        PrintJson($users);
    }
}

function GetSelectedUserPhotos($userId)
{
    global $connect;

    $request = "SELECT * FROM `photo` WHERE `photo`.`OwnerId` = '$userId'";
    $posts = $connect->query($request);
    if (isset($posts))
        PrintJson($posts);
}

function GetUserPosts($userId)
{
    global $connect;

    $request = "SELECT * FROM `post` WHERE `post`.`OwnerId` = '$userId'";
    $posts = $connect->query($request);
    PrintJson($posts);
}


function Post($urlData, $formData)
{
    switch (sizeof($urlData)) {
        case 0:
            RegisterUser($formData);
            break;
        case 2:
            if ($urlData[1] == "avatar") {
                if (is_numeric($urlData[0]))
                    ChangeAvatar($urlData[0]);
            } else {
                if ($urlData[1] == "messages") {
                    SendMessage($urlData, $formData);
                } else {
                    http_response_code(400);
                }
            }
            break;
        default:
            http_response_code(501);
            break;
    }
}

function ChangeAvatar($userId)
{
    $headers = getallheaders();
    global $connect;

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
        $token = str_replace("Bearer ", "", $headers["Authorization"]);
        $currentUserId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

        if ($generalAccessLevel == ADMIN_ACCESS_LEVEL || $currentUserId == $userId) {

            $allowedTypes = array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF);
            $detectedType = exif_imagetype($_FILES['File']['tmp_name']);
            if ($_FILES && $_FILES["File"]["error"] == UPLOAD_ERR_OK
                && in_array($detectedType, $allowedTypes)) {

                $name = htmlspecialchars(basename($_FILES["File"]["name"]));
                $path = "Uploads/Avatars/" . time() . $name;
                if (move_uploaded_file($_FILES["File"]["tmp_name"], $path)) {
                    $request = "SELECT Avatar FROM `user` WHERE `user`.Id = $userId";
                    $avatar = mysqli_fetch_array($connect->query($request))[0];

                    if ($avatar != null) {
                        if (!unlink(substr($avatar, 1))) {
                            echo 'Error while deleting old avatar from server.';
                        }
                    }
                    $request = "UPDATE `user` SET Avatar = '/$path' WHERE Id = $userId";
                    $connect->query($request);
                    $request = "SELECT `user`.Id, `user`.Name, Surname, Username, Birthday, Avatar, Status, CASE WHEN `user`.`CityId` IS NOT NULL THEN `city`.Name END as City, `role`.Name as Role FROM user JOIN role ON `role`.Id = `user`.RoleId LEFT JOIN city ON `city`.Id = `user`.CityId WHERE `user`.Id = $userId";
                    PrintJson($connect->query($request));
                    exit();
                } else {
                    echo 'ERROR';
                }
            }
        } else {
            http_response_code(401);
            exit();
        }
    } else {
        http_response_code(401);
        exit();
    }
}

function SendMessage($urlData, $formData)
{
    $headers = getallheaders();
    global $connect;

    $recieverId = $urlData[0];
    $message = $formData->message;

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
        $token = str_replace("Bearer ", "", $headers["Authorization"]);
        $currentUserId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

        $request = "INSERT INTO `message`(`Text`, `Date`, `SenderId`, `RecieverId`)
         VALUES ('$message',NOW(),$currentUserId,$recieverId)";
        $connect->query($request);

        $request = "SELECT MAX(Id) as Id FROM `message`";

        PrintJson($connect->query($request));
    } else {
        http_response_code(401);
        exit();
    }
}

function RegisterUser($formData)
{
    $headers = getallheaders();
    global $connect;

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    $name = $formData->Name;
    $surname = $formData->Surname;
    $username = $formData->Username;
    $password = $formData->Password;
    $birthday = null;
    if (isset($formData->Birthday)) {
        $birthday = $formData->Birthday;
    }

    if ($generalAccessLevel == ADMIN_ACCESS_LEVEL || $generalAccessLevel == UNAUTHORIZED_ACCESS_LEVEL) {
        CheckForUniqueUsername($username);

        $connect->query("INSERT INTO `user` (`Id`, `Name`, `Surname`, `Password`, `Birthday`, `Avatar`, `Status`, `Username`, `Token`, `CityId`, `RoleId`)
                    VALUES (NULL, '$name', '$surname', '$password', '$birthday', NULL, NULL, '$username', NULL, NUll, 3)");
    }

    if ($generalAccessLevel == UNAUTHORIZED_ACCESS_LEVEL) {
        global $connect;
        $token = generateToken();
        $connect->query("UPDATE `user` SET `Token` = '$token' WHERE `user`.`Username` = '$username' AND `user`.`Password` = '$password'");

        echo json_encode($token);
    }
}

function CheckForUniqueUsername($username, $id = null)
{
    global $connect;

    $similarUser = $connect->query("SELECT * FROM `user` WHERE `user`.`Username` = '$username'");
    if ($similarUser->num_rows != 0) {
        if ($similarUser->num_rows == 1 && $id != NULL) {
            if ($similarUser->fetch_array()['Id'] == $id) {
                return;
            }
        }
        http_response_code(409);
        echo json_encode([
            'status' => false,
            'message' => 'Username already used'
        ]);
        exit();
    }
}

function Patch($urlData, $formData)
{
    switch (sizeof($urlData)) {
        case 1:
            if (is_numeric($urlData[0])) {
                EditUser($urlData, $formData);
            } else {
                http_response_code(400);
            }
            break;
        case 2:
            switch ($urlData[1]) {
                case "city":
                    SetUserCity($urlData, $formData);
                    break;
                case "status":
                    SetUserStatus($urlData, $formData);
                    break;
                case "role":
                    SetUserRole($urlData, $formData);
                    break;
                default:
                    http_response_code(501);
                    break;
            }
            break;
        default:
            http_response_code(501);
            break;
    }
}

function EditUser($urlData, $formData)
{
    $headers = getallheaders();
    global $connect;

    $userId = $urlData[0];
    $name = $formData->Name;
    $surname = $formData->Surname;
    $username = $formData->Username;
    $password = $formData->Password;
    $birthday = null;
    if (isset($formData->Birthday)) {
        $birthday = $formData->Birthday;
    }
    $avatar = $formData->Avatar;

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
        $token = str_replace("Bearer ", "", $headers["Authorization"]);
        $currentUserId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

        if ($generalAccessLevel == ADMIN_ACCESS_LEVEL || $currentUserId == $userId) {


            $newRequest = 'UPDATE `user` SET ';
            if ($name != "") {
                $newRequest .= "Name='$name', ";
            }

            if ($surname != "") {
                $newRequest .= "Surname='$surname', ";
            }

            if ($username != "") {
                CheckForUniqueUsername($username);
                $newRequest .= "UserName='$username', ";
            }

            if ($password != "") {
                $newRequest .= "Password='$password', ";
            }

            if ($birthday != "") {
                $newRequest .= "Birthday='$birthday', ";
            }

            if ($avatar != "") {
                $newRequest .= "Avatar='$avatar' ";
            } else {
                mb_substr($newRequest, 0, -2);
            }


            $newRequest .= "WHERE Id=$userId";
            if (!$connect->query($newRequest)) {
                echo "U've mistaken";
            }

            $request = "SELECT * FROM user WHERE Id =$userId";

            $user = $connect->query($request);

            unset($user->Password);
            PrintJson($user);
        } else {
            http_response_code(401);
            exit();
        }
    } else {
        http_response_code(401);
        exit();
    }

}

function SetUserCity($urlData, $formData)
{
    $headers = getallheaders();
    global $connect;

    $userId = $urlData[0];
    $cityId = $formData->CityID;

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
        $token = str_replace("Bearer ", "", $headers["Authorization"]);
        $currentUserId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

        if ($generalAccessLevel == ADMIN_ACCESS_LEVEL || $currentUserId == $userId) {
            $request = "UPDATE `user` SET `CityId` = $cityId WHERE `user`.Id = $userId";
            $connect->query($request);
        }
    } else {
        http_response_code(401);
        exit();
    }
}

function SetUserStatus($urlData, $formData)
{
    $headers = getallheaders();
    global $connect;

    $userId = $urlData[0];
    $status = $formData->status;

    if ($status == "Online" || $status == "Offline" || $status == "Do not disturb" || $status == "In panic" || $status == "Want to die") {

        if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
        else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

        if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
            $token = str_replace("Bearer ", "", $headers["Authorization"]);
            $currentUserId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

            if ($generalAccessLevel == ADMIN_ACCESS_LEVEL || $currentUserId == $userId) {
                $request = "UPDATE `user` SET `Status` = '$status' WHERE `user`.Id = $userId";
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
        http_response_code(400);
    }
}

function SetUserRole($urlData, $formData)
{
    $headers = getallheaders();
    global $connect;

    $userId = $urlData[0];
    $roleId = $formData->RoleID;

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {
        $request = "UPDATE `user` SET `RoleId` = $roleId WHERE `user`.Id = $userId";
        $connect->query($request);
    } else {
        http_response_code(401);
        exit();
    }
}


function Delete($urlData)
{
    $headers = getallheaders();
    global $connect;

    $userId = $urlData[0];

    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {
        $request = "DELETE FROM `user` WHERE `user`.`Id` = $userId";
        $connect->query($request);
    } else {
        http_response_code(401);
        exit();
    }
}

?>