<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

$id = "";
$name = "";
$birthday = "";
$address = "";
$land = "";
$unit = "";
$phone = "";


$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!isset($_GET["id"])) {
        header("location: /CAFCA-MS/dashboard/farmers_sec/farmers.php");
        exit;
    }

    $id = $_GET["id"];

    $sql = "SELECT * FROM farmers WHERE id=$id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if (!$row) {
        header("location: /CAFCA-MS/dashboard/farmers_sec/farmers.php");
        exit;
    }

    $name = $row["name"];
    $birthday = $row["birthday"];
    $address = $row["address"];
    $land = $row["land"];
    $unit = $row["unit"];
    $phone = $row["phone"];
} else {
    $id = $_POST["id"];
    $name = $_POST["name"];
    $birthday = $_POST["birthday"];
    $address = $_POST["address"];
    $land = $_POST["land"];
    $unit = $_POST["unit"];
    $phone = $_POST["phone"];

    do {
        if (empty($id) || empty($name) || empty($birthday) || empty($address) || empty($land) || empty($unit) || empty($phone)) {
            $errorMessage = "All fields are required!";
            break;
        }

        $sql = "UPDATE farmers " .
            "SET name = '$name', birthday = '$birthday', address = '$address', land = '$land', unit = '$unit', phone = '$phone' " .
            "WHERE id = $id";

        $result = $conn->query($sql);

        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $successMessage = "Farmer's info successfully updated!";

        header("location: /CAFCA-MS/dashboard/farmers_sec/farmers.php");
        exit;

    } while (true);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Farmers</title>
    <link rel="stylesheet" href="	https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

    <div class="container my-5">
        <h2>Edit farmer's information</h2>

        <?php
        if (!empty($errorMessage)) {
            echo "
            <div class='alert alert-warning alert-dismissible fade show' role='alert'>
                <strong>$errorMessage</strong>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
            ";
        }
        ?>
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Name</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="name" value="<?php echo $name; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Birthday</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="birthday" value="<?php echo $birthday; ?>"
                        max="<?= date('Y-m-d', strtotime('-15 years')) ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Address</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="address" value="<?php echo $address; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Land Area</label>
                <div class="col-sm-3">
                    <input type="number" step="0.25" class="form-control" name="land" value="<?php echo $land; ?>">
                </div>
                <div class="col-sm-3">
                    <select class="form-select" name="unit">
                        <option value="N/A">---</option>
                        <option value="cm²">cm²</option>
                        <option value="m²">m²</option>
                        <option value="km²">km²</option>
                        <option value="hectare(s)">hectare(s)</option>
                        <option value="acre(s)">acre(s)</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Contact Number</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="phone" value="<?php echo $phone; ?>">
                </div>
            </div>



            <?php
            if (!empty($successMessage)) {
                echo "
                <div class='row mb-3'>
                    <div class='offset-sm-3 col-sm-6'>
                        <div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <strong>$successMessage</strong>
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>
                    </div>
                </div>
                ";
            }
            ?>
            <div class="row mb-3">
                <div class="offset-sm-3 col-sm-3 d-grid">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
                <div class="col-sm-3 d-grid">
                    <a class="btn btn-outline-primary" href="/CAPSTONE/CAFCA-MS/dashboard/farmers_sec/farmers.php"
                        role="button">Cancel Editing</a>
                </div>
            </div>
        </form>
    </div>


</body>

</html>