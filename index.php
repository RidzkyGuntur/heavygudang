<?php
session_start();
include 'dbconnect.php';

if(isset($_SESSION['role'])){
    header("location:stock");
}

if(isset($_GET['pesan'])){
    if($_GET['pesan'] == "gagal"){
        echo "<div class='alert alert-danger'>Username atau Password salah!</div>";
    }else if($_GET['pesan'] == "logout"){
        echo "<div class='alert alert-success'>Anda berhasil keluar dari sistem</div>";
    }else if($_GET['pesan'] == "belum_login"){
        echo "<div class='alert alert-warning'>Anda harus Login</div>";
    }else if($_GET['pesan'] == "noaccess"){
        echo "<div class='alert alert-danger'>Akses Ditutup</div>";
    }
}

if(isset($_POST['btn-login']))
{
    $uname = mysqli_real_escape_string($conn,$_POST['username']);
    $upass = mysqli_real_escape_string($conn,md5($_POST['password']));

    // menyeleksi data user dengan username dan password yang sesuai
    $login = mysqli_query($conn,"SELECT * FROM slogin WHERE username='$uname' AND password='$upass';");
    // menghitung jumlah data yang ditemukan
    $cek = mysqli_num_rows($login);
    
    // cek apakah username dan password di temukan pada database
    if($cek > 0){
        $data = mysqli_fetch_assoc($login);
    
        if($data['role']=="stock"){
            // buat session login dan username
            $_SESSION['user'] = $data['nickname'];
            $_SESSION['user_login'] = $data['username'];
            $_SESSION['id'] = $data['id'];
            $_SESSION['role'] = "stock";
            header("location:stock");
        } else if($data['role']=="supplier") {
            // buat session login dan username
            $_SESSION['user'] = $data['nickname'];
            $_SESSION['user_login'] = $data['username'];
            $_SESSION['id'] = $data['id'];
            $_SESSION['role'] = "supplier";
            header("location:supplier");
        } else {
            header("location:index.php?pesan=gagal");
        }
    } else {
        header("location:index.php?pesan=gagal");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Form</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-144808195-1"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'UA-144808195-1');
    </script>
    <style>
        body {
            background-image: url('https://source.unsplash.com/1600x900/?warehouse,inventory'); /* URL background yang menarik */
            background-size: cover;
            background-repeat: no-repeat;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.9);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0px 0px 20px 0px #000;
            width: 100%;
            max-width: 600px;
        }
        .login-container h2 {
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        .login-container .form-group input {
            border-radius: 20px;
            height: 50px;
            font-size: 1.2em;
        }
        .login-container button {
            border-radius: 20px;
            height: 50px;
            font-size: 1.2em;
        }
        .alert {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            text-align: center;
            font-size: 1.2em;
        }
        .logo img {
            width: 150px; /* Ubah ukuran gambar sesuai kebutuhan */
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .login-container {
                padding: 20px;
                width: 90%;
                max-width: 90%;
            }
            .login-container h2 {
                font-size: 2em;
            }
            .login-container .form-group input,
            .login-container button {
                font-size: 1em;
                height: 45px;
            }
            .alert {
                font-size: 1em;
            }
        }
        @media (max-width: 576px) {
            .login-container {
                padding: 20px;
                width: 95%;
                max-width: 95%;
            }
            .login-container h2 {
                font-size: 1.8em;
            }
            .login-container .form-group input,
            .login-container button {
                font-size: 0.9em;
                height: 40px;
            }
            .alert {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="login-container text-center">
        <div class="logo">
            <img src="heavy.png" alt="Heavy Logo">
        </div>
        <h2>Login Form</h2>
        <div style="color:black">
            <label>Silahkan Masukan Username dan Password</label><br>
        </div>
        <form method="post">
            <div class="form-group">
                <input type="text" class="form-control" placeholder="Username" name="username" autofocus required>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" placeholder="Password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block" name="btn-login">Masuk</button>
        </form>
    </div>
</body>
</html>
