<?php
session_start();
include 'db.php';
include 'google_config.php';

if (isset($_GET['code'])) {
    
    // 1. Get the Auth Code
    $code = $_GET['code'];

    // 2. Exchange Code for Access Token
    $url = 'https://oauth2.googleapis.com/token';
    $params = [
        'code'          => $code,
        'client_id'     => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri'  => $google_redirect_url,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        
        // 3. Get User Profile Data
        $user_info_url = 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $token_data['access_token'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $user_info = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $g_email = $user_info['email'];
        $g_name  = $user_info['name'];
        $g_id    = $user_info['id'];
        
        // 4. CHECK DATABASE
        $check = $conn->query("SELECT * FROM users WHERE email = '$g_email'");

        if ($check->num_rows > 0) {
            // --- USER EXISTS ---
            $row = $check->fetch_assoc();
            
            // Link Google ID if missing
            if (empty($row['google_id'])) {
                $conn->query("UPDATE users SET google_id = '$g_id', is_verified = 1 WHERE email = '$g_email'");
            }

            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['user_name'] = $row['full_name'];
            $_SESSION['user_role'] = $row['role'];
            
            if ($row['role'] == 'landlord') {
                header("Location: dashboard.php");
            } elseif ($row['role'] == 'admin') {
                header("Location: admin_panel.php");
            } else {
                header("Location: index.php");
            }
            exit;

        } else {
            // --- NEW USER (FIXED) ---
            $default_role = 'student'; 
            $default_pass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            
            // FIX: Added 'phone' column with a placeholder value '0000000000'
            $sql = "INSERT INTO users (full_name, email, phone, password, role, is_verified, google_id) 
                    VALUES ('$g_name', '$g_email', '0000000000', '$default_pass', '$default_role', 1, '$g_id')";
            
            if ($conn->query($sql)) {
                $new_id = $conn->insert_id;
                $_SESSION['user_id'] = $new_id;
                $_SESSION['user_name'] = $g_name;
                $_SESSION['user_role'] = $default_role;
                
                header("Location: index.php");
                exit;
            } else {
                die("Error creating account: " . $conn->error);
            }
        }
    } else {
        die("Failed to authenticate with Google.");
    }
} else {
    header("Location: login.php");
    exit;
}
?>