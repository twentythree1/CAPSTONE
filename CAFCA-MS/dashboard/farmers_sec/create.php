<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);


$name = "";
$birthday = "";
$address = "";
$land = "";
$unit = "";
$phone = "";


$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST["name"];
    $birthday = $_POST["birthday"];
    $address = $_POST["address"];
    $land = $_POST["land"];
    $unit = $_POST["unit"];
    $phone = $_POST["phone"];


    do {
        if (empty($name) || empty($birthday) || empty($address) || empty($land) || empty($unit) || empty($phone)) {
            $errorMessage = "All fields are required!";
            break;
        }

        $birthdayDate = new DateTime($birthday);
        $today = new DateTime();
        $age = $birthdayDate->diff($today)->y;

        if ($age < 15) {
            $errorMessage = "Farmer must be at least 15 years old.";
            break;
        }

        // adding farmer
        $sql = "INSERT INTO farmers (name, birthday, address, land, unit, phone)" . "VALUES ('$name', '$birthday', '$address', '$land', '$unit', '$phone')";
        $result = $conn->query($sql);

        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $name = "";
        $birthday = "";
        $address = "";
        $land = "";
        $unit = "";
        $phone = "";

        $successMessage = "Farmer successfully added!";

        header("location: /CAFCA-MS/dashboard/farmers_sec/farmers.php");
        exit;

    } while (false);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Farmers</title>
    <link rel="stylesheet" href="	https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

    <div class="container my-5">
        <h2>Add a new Farmer</h2>

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
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Name</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="name" value="<?php echo $name; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Birthday</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="birthday"
                        max="<?= date('Y-m-d', strtotime('-15 years')) ?>" value="<?php echo $birthday; ?>">
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
                        <option value="acres">km²</option>
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
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
                <div class="col-sm-3 d-grid">
                    <a class="btn btn-outline-primary" href="/CAPSTONE/CAFCA-MS/dashboard/farmers_sec/farmers.php"
                        role="button">Cancel</a>
                </div>
            </div>
        </form>
    </div>

</body>

</html>