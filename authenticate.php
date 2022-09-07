<?php // authenticate.php

require_once 'login.php';
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error) {
    die(mysql_fatal_error());
}


session_start();
//solves for session fixation by generating a new session id
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = 1; 
}
if (!isset($_SESSION['count'])) {
    $_SESSION['count'] = 0; 
} else {
    ++$_SESSION['count'];
}

$name = $temp_pw = $email = "";

if (isset($_POST['username']))
    $name = sanitize($conn, $_POST['username']);
if (isset($_POST['password']))
    $temp_pw = sanitize($conn, $_POST['password']);
if (isset($_POST['email']))
    $email = sanitize($conn, $_POST['email']);

$fail = validate_name($name);
$fail .= validate_password($temp_pw);
$fail .= validate_email ($email);

echo "<!DOCTYPE html>";
echo "<html><head><title>Login</title>";

if (isset($_POST['attemptRegister']) && $fail == "") {
    registerUser($conn, $name, $temp_pw, $email);
}

if (isset($_POST['attemptLogin']) && $fail == "") {
    loginUser($conn, $name, $temp_pw, $email);
}

// webpage displays table with options enter student information for register or login
// information such as name, password, ID, and email
// verifies user's input on the client computer

echo <<<_END
<style>
    .signup
    {
        border: 1px solid #999999;
        font: normal 14px helvetica; color: #444444;
    }
</style>
<script>
function validate(form) {
    fail = validateName(form.username.value)
    fail += validatePassword(form.password.value)
    fail += validateEmail(form.email.value)

    if (fail == "") return true
    else { alert(fail); return false }
}

function validateName(field) {
    if (field.length < 3 || 60 < field.length) {
        return "Username must be between 3 and 59 characters. ";
    } else if (/[^a-zA-Z ]/.test(field)) {
        return "Username must only contain alphabetical characters and spaces. "
    }
    return "";   
}

function validatePassword(field) {
    if (field.length < 9 || 59 < field.length) {
        return "Password must be between 9 and 59 characters. ";
    } else if (field.match('/[^\x20-\x7e]/')) {
        return "Only ASCII charcter inputs for password. ";
    } else if (!/[a-z]/.test(field) || !/[A-Z]/.test(field) ||
                !/[0-9]/.test(field)) {
        return "Passwords require 1 each of a-z, A-Z, and 0-9. "
    }
    return ""
}

function validateEmail(field) {
    if (field.length < 9 || 59 < field.length) {
         return "The email must be between 9 and 59 characters. "
    } else if (field.match('/[^\x20-\x7e]/')) {
        return "Only ASCII charcter inputs for email. " 
    } else if (!(field.indexOf(".") > 0) || !field.indexOf("@") > 0) {
        return "email must have @ character and at least one . character. "
    } else if (field.match("/[^a-zA-Z0-9.@_-]/")) {
        return "The email address can only contains character a-zA-Z0-9.@_- "
    }
    return ""
}

</script>
</head>
<body>
    <table align="center" border="O" cellpadding="2" cellspacing="5" bgcolor="#eeeeee"
        <tr><th colspan="2" align="center">Login or Register</th></tr>
        <tr><td colspan="2"><p><font color="RED" size=2><i>$fail</i></font></p></td></tr>
        <form method="post" action="authenticate.php" onsubmit="return validate(this)">
            <tr><td>Username</td>
            <td><input type="text" maxlength="32" name="username" value="$name">
            </td></tr><tr><td>Password</td>
            <td><input type="password" maxlength="60" name="password" value="$temp_pw">
            </td></tr><tr><td>Email</td>
            <td><input type="text" maxlength="64" name="email" value="$email">
            </td></tr><tr><td colspan="2" align="center"><input type="submit" 
            name = "attemptLogin" value="Login"></td></tr>
            </td></tr><tr><td colspan="2" align="center"><input type="submit"
            name = "attemptRegister" value ="Register"></td></tr>
        </form>
    </table>
</body>
</html>
_END;

$conn->close();

//validation functions that verfiy users inputs from the server side

function validate_name($field) {
    if (strlen($field) < 6 || 60 < strlen($field)) {
        return "Username must be between 6 and 59 characters.<br>";
    } else if(preg_match("/[^a-zA-Z ]/", $field)) {
        return "Username must only contain alphabetical characters and paces.<br>";
    }
    return "";   
}

function validate_password($field) {
    if (strlen($field) < 9 || 59 < strlen($field)) {
        return "Password must be between 9 and 59 characters.<br>";
    } else if (preg_match('/[^\x20-\x7e]/', $field)) {
        return "Only ASCII charcter inputs for password.<br>";
    } else if (!preg_match("/[a-z]/", $field) ||
            !preg_match("/[A-Z]/", $field) ||
            !preg_match("/[0-9]/", $field)) {
        return "Passwords require 1 each of a-z, A-Z, and 0-9<br>";
    }
    return "";
}

function validate_email($field) {
    if (strlen($field) < 9 || 59 < strlen($field)) {
         return "The email must be between 9 and 59 characters.<br>";
    } else if (preg_match('/[^\x20-\x7e]/', $field)) {
        return "Only ASCII charcter inputs for email.<br>"; 
    } else if ((substr_count($field, ".") == 0) || (substr_count($field, "@") == 0)) {
        return "Invalid entry.";
    } else if (preg_match("/[^a-zA-Z0-9.@_-]/", $field)) {
        return "The email address can only contains character a-zA-Z0-9.@_-<br>";
    }
    return "";
}

//registers users using username, password, and email. populates their data into the credentials database. 
//makes sure the username and email are not already in the credentials database before registering.
//uses password_hash to encrype password
function registerUser($conn, $name, $temp_pw, $email) {
    $query = "SELECT * FROM credentials WHERE username='$name'";
    $result = $conn->query($query);
    if ($result->num_rows == 0) {
        $query = "SELECT * FROM credentials WHERE email='$email'";
        $result = $conn->query($query);
        if ($result->num_rows == 0) {
            $temp_pw = sanitize($conn, $_POST['password']);
            $token = password_hash($temp_pw, PASSWORD_BCRYPT);
            $query = "INSERT INTO credentials(token, username ,email) VALUES('$token', '$name', '$email')";
            $conn->query($query);
            $query = "SELECT * FROM credentials WHERE email='$email'";
            $result = $conn->query($query);
            if ($result->num_rows == 0) {
                echo "registration error. <br>";
            }
            else {
                $record = $result->fetch_array(MYSQLI_NUM);
                $result->close();
                $tableName = "cookbook";
                $tableName .= $record[2];
                $sql = "CREATE TABLE `dataCollections`.`{$tableName}` (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, recipeName VARCHAR(30) NOT NULL, ingredients VARCHAR(30) NOT NULL)";
                $conn->query($sql);
            }
            echo "You have been registered. <br>";
        } else {
            $result->close();
            echo "The username or email is already registered. Try again.";
        }
    } else {
        $result->close();
        echo "The username or email is already registered.  Try again.";
    }
}

//logs users in if their credentials are matched with the corresponding record in the credentials database
//uses password_verify function to decrypt password
function loginUser($conn, $name, $temp_pw, $email) {
    $query = "SELECT * FROM credentials WHERE username='$name'";
    $result = $conn->query($query);
    if ($result->num_rows == 0) {
        echo "Invalid username or password. <br>";
    } else {
        $record = $result->fetch_array(MYSQLI_NUM);
        $result->close();
        if (password_verify($temp_pw, $record[0]) && $name == $record[3]) {
            $_SESSION['check'] = hash('ripemd128', $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
            $_SESSION['password'] = $temp_pw;
            $_SESSION['id'] = $record[2];
            $_SESSION['username'] = $record[3];
            $_SESSION['email'] = $record[1];
            $conn->close();
            header('Location: '.'cucinare.php');
            die();
        } else {
            echo "Invalid username or password. <br>";
        }
    }
}

function mysql_fatal_error() {
    echo <<< _END
    Sorry, the task you requested to complete cannot be done.
    Please refresh the page and try again. Contact our admin
    if you are still having problems <a href="johndoe@localhost"></a>
    _END;
}
//solves for html and sql injections
function sanitize($conn, $string) {
    if(get_magic_quotes_gpc()) {
        $string = stripslashes($string);
    }
    return htmlentities($conn->real_escape_string($string));
}

