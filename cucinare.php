<?php

require_once 'login.php';
$conn = new mysqli($hn, $un, $pw, $db);

if ($conn->connect_error) {
    die(mysql_fatal_error());
}

//starts session with max lifetime of one day

ini_set('session.gc_maxlifetime', 86400);
session_start();

//solves for session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = 1; 
}
if (!isset($_SESSION['count'])) {
    $_SESSION['count'] = 0; 
} else {
    ++$_SESSION['count'];
}

$name = $table = "";
$tableData = False;


//check if user wants logs out or was directed from a session
//if session is not identified or user wants to log out, session is destroyed
//and user is redirected to the main page
if (!isset($_SESSION['username']) || !isset($_SESSION['password']) 
    || !isset($_SESSION['email']) || $_SESSION['check'] 
    != hash('ripemd128', $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']) 
    || isset($_POST["logout"])) {
    $conn->close();
    destroy_session_and_data();
    header('Location: '.'authenticate.php');
    die();
}

if (isset($_POST['dishName']))
    $name = sanitize($conn, $_POST['dishName']);
if (isset($_POST['txtbox']) && $_POST['numbox']) {
    $table = sanitizeTable($conn);
    $tableData = getTableStatus($table, $name);
}

$id = sanitize($conn, $_SESSION['id']);

// Submits ingredients to users cookbook if user clicks submit

if (isset($_POST['tableSubmit']) && $tableData != False) {
    $query = "SELECT * FROM credentials WHERE id='$id'";
    $result = $conn->query($query);
    if ($result->num_rows == 0) {
        echo "Error saving recipe  <br>";
    } else {
        $record = $result->fetch_array(MYSQLI_NUM);
        $result->close();
        $tableName = "cookbook";
        $tableName .= $record[2];
        $query = "INSERT INTO $tableName (recipeName, ingredients) VALUES ('$name', '$tableData')";
        $conn->query($query);
    }
}


//webpage displays table with options enter ingredients for a recipe in their cookbook.
//displays current state of user's cookbook
//also contains functions in javscript for client side validation of inputs.

echo <<< _END
<!DOCTYPE html>
<html>
<head>
<title> 
Create Recipe 
</title>
<style>
.divtable {
    display: table;         
    width: auto;         
    background-color: #eee;         
    border: 1px solid #666666;         
    border-spacing: 5px;
  }
  .div-table-row {
    display: table-row;
    width: auto;
    clear: both;
  }
  .div-table-col {
    float: left;
    display: table-column;         
    width: 200px;         
    background-color: #ccc;  
  }
</style>
<script>
function addRow(tableID) {
    var table = document.getElementById(tableID);
    var rowCount = table.rows.length;
    var row = table.insertRow(rowCount);

    var cell1 = row.insertCell(0);
    var element1 = document.createElement("input");
    element1.type = "checkbox";
    element1.name="chkbox[]";
    cell1.appendChild(element1);
    var cell2 = row.insertCell(1);
    var element2 = document.createElement("input");
    element2.type = "number";
    element2.name = "numbox[]";
    cell2.appendChild(element2); 
    var cell3 = row.insertCell(2);
    var element3 = document.createElement("input");
    element3.type = "text";
    element3.name = "txtbox[]";
    cell3.appendChild(element3); 

}

function deleteRow(tableID) {
    try {
        var table = document.getElementById(tableID);
        var rowCount = table.rows.length;
        for(var i= 1; i<rowCount; i++) {
            var row = table.rows[i];
            var chkbox = row.cells[0].childNodes[0];
            if(null != chkbox && true == chkbox.checked) {
                table.deleteRow(i);
                rowCount--;
                i--;
            }
        }
    } catch(e) {
        alert(e);
    }
}

function getTableStatus(tableID) {
    var table = document.getElementById(tableID)
    for (var i = 1; i < table.rows.length; i++) {
        var objCells = table.rows.item(i).cells
        var quantity = objCells.item(1).childNodes[0].value
        var ingredient = objCells.item(2).childNodes[0].value
        valid = validate(quantity, ingredient)
        if (!valid) {
            return false
        }
    }
    return true
}

function validate(quantity, ingredient) {
    fail = validateQuantity(quantity)
    fail += validateIngredients(ingredient)
    if (fail == "") {
        return true
    } else { 
        alert(fail)
        return false 
    }
}

function validateName(field) {
    if (field.length < 3 || 60 < field.length) {
        alert("For the dish name, only 3 to 59 characters/space is allowed")
        return false
    } else if (/[^a-zA-Z ]/.test(field)) {
        alert("For the dish name, only 3 to 59 characters/space is allowed")
        return false
    }
    return true;   
}

function validateQuantity(field) {
    if (field > 100 || /[^0-9]/.test(field)) {
        return "Quantity must be a number less than 100. "
    }
    return ""
}

function validateIngredients(field) {
    var maxNumCharacters = 65535
    if (field.length > maxNumCharacters) {
        return "Input for ingredients can only be 65535 characters long."
    } else if (/[^a-zA-Z ]/.test(field)) {
        return "Ingredients must only contain alphabetical characters and spaces. "
    }
    return "";
}

</script>
</head>
<body>
    <form method="post" action="cucinare.php" onsubmit="return getTableStatus('dataTable') && validateName(this.dishName.value)">
    <input type="button" value="Add Ingredient" onclick="addRow('dataTable')"/>
    <input type="button" value="Delete Ingredient" onclick="deleteRow('dataTable')"/>
    <input type="submit" name = "tableSubmit" value="Submit"><br>
    Recipe: <input type="text" name="dishName" maxlength="60" value="$name"/><br>
    <table id="dataTable" width="350px" border="1">
    <tr><td>Select to Delete:</td><td>Quantity:</td><td>Ingredient:</td></tr>
    </table>
    </form>  
</body>
</html>
_END;

$tableName = "cookbook";
$tableName .= $id;
$query = "SELECT * FROM $tableName";
$result = $conn->query($query);
$row_count = $result->num_rows;
echo <<<_END
<div class='div-table'> 
    <div class='div-table-row'>
        <div class='div-table-col'>Recipes</div>
        <div class='div-table-col'>Ingredients</div>
    </div>
_END;
for ($i=0; $i<$row_count; $i++)
{
    $row_users = $result->fetch_array(MYSQLI_NUM);
    echo "<div class='div-table-row'><div class='div-table-col'>$row_users[1]</div><div class='div-table-col'>$row_users[2]</div></div>";
}
$result->close();

echo "</div>";
echo "</body></html>";

echo <<<_END
<html><head></head><body><form method="post" action="cucinare.php"> 
<input type="submit" name="logout" value="Logout">
</form>
_END;

$conn->close();

//user input validation functions on server side


//sanitizes each ingredient and quantity input in the table
function sanitizeTable($conn) {
    $newTable = array();
    for ($i = 0; $i < count($_POST['txtbox']); $i++) {
        $ingredient = sanitize($conn, $_POST['txtbox'][$i]);
        $quantity = sanitize($conn, $_POST['numbox'][$i]);
        $newEntry = array();
        array_push($newEntry, $quantity);
        array_push($newEntry, $ingredient);
        array_push($newTable, $newEntry);
    }
    return $newTable;
}

//returns a string that contains the table information
/*can possibly cause a runtime error if table is too large
for the capacity of the string varialble */

function getTableStatus($table, $name) {
    $tableString = "";
    $tableSize = count($table);
    $validName = validate_name($name);
    if(!$validName)
        return False;
    for ($i = 0; $i < $tableSize - 1; $i++) {
        $quantity = $table[$i][0];
        $ingredient = $table[$i][1];
        $validData = validate($quantity, $ingredient);
        if (!$validData) {
            return False;
        }
        $tableString .= $quantity;
        $tableString .= "x ";
        $tableString .= $ingredient;
        $tableString .= ", ";
    }
    $quantity = $table[$tableSize - 1][0];
    $ingredient = $table[$tableSize - 1][1];
    $validData = validate($quantity, $ingredient);
        if (!$validData) {
            return False;
        }
    $tableString .= $quantity;
    $tableString .= "x ";
    $tableString .= $ingredient;
    return $tableString;
}

function validate($quantity, $ingredient) {
    $fail = validate_quantity($quantity);
    $fail .= validate_ingredients($ingredient);
    if ($fail == "") {
        return True;
    } else { 
        return False; 
    }
}


function validate_name($field) {
    $nameLength = strlen($field);
    if ($nameLength < 3 || 60 < $nameLength) {
        return False;
    } elseif(preg_match("/[^a-zA-Z ]/", $field)) {
        return False;
    }
    return True;   
}

function validate_quantity($field) {
    if ($field > 100 || preg_match("/[^0-9]/", $field)) {
        return "Quantity must be a number less than 100. ";
    }
    return "";
}

function validate_ingredients($field) {
    $maxNumCharacters = 65535;
    if (strlen($field) > $maxNumCharacters) {
        return "Input for ingredients can only be 65535 characters long.<br>";
    } elseif (preg_match("/[^a-zA-Z0-9 ]/", $field)) {
        return "Ingredients must only contain alphabetical and spaces.<br>";
    }
    return "";
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


function destroy_session_and_data() {
    $_SESSION = array();
    setcookie(session_name(), '', time() - 2592000, '/'); 
    session_destroy();
}