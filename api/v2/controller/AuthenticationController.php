<?php
namespace App\API\V2\Controller;

class AuthenticationController
{
	public function Login()
	{
		global $pdo;

		$email = $_POST['email'];
		$password = $_POST['password'];
		$sql = "SELECT * FROM users WHERE email = :email";
		$query = $pdo->prepare($sql);
		$query->bindValue(':email', $email, \PDO::PARAM_STR);
		$query->execute();
		$selected_user = $query->fetch();

		$arr = ['token' => '', 'uid' => ''];
		// Check if user exists
		if (!$selected_user) {
			$arr['token'] .= "Failed";
			$json = json_encode($arr);
			echo $json;
			exit();
		}
		if (password_verify($password, $selected_user->password)) {
			$userid = $selected_user->id;
			$cstrong = true;
			$token = bin2hex(openssl_random_pseudo_bytes(64, $cstrong));
			$stoken = sha1($token);
			$sql2 = "INSERT INTO login_tokens (`token`, `user_id`) VALUES (:stoken , :userid )";
			$query2 = $pdo->prepare($sql2);
			$query2->bindValue(':stoken', $stoken, \PDO::PARAM_STR);
			$query2->bindValue(':userid', $userid, \PDO::PARAM_STR);
			$query2->execute();
			setcookie("LOGINTOKEN", $token, time() + 60 * 60 * 24 * 365, "/");
			$arr['token'] .= $token;
			$arr['uid'] .= $userid;
			$json = json_encode($arr);
			\Helpers::addAuditLog("LOGGED_IN::{$_SERVER['REMOTE_ADDR']} Logged Into Account ID:{$userid} Username:{$selected_user->username}");
			echo $json;
		} else {
			\Helpers::addAuditLog("AUTHENTICATION_FAILED::{$_SERVER['REMOTE_ADDR']} Triggered An Unauthenticated Response In `Login`");
			$arr['token'] .= "Failed";
			$json = json_encode($arr);
			echo $json;
		}
	}
	public function Logout()
	{
		global $pdo;
		if (isset($_COOKIE['LOGINTOKEN'])) {
			$token = sha1($_COOKIE['LOGINTOKEN']);
			$sql = "SELECT token FROM login_tokens WHERE token = :token";
			$query = $pdo->prepare($sql);
			$query->bindValue(':token', $token, \PDO::PARAM_STR);
			$query->execute();
			$result = $query->fetch();
			if ($result) {
				$sql2 = 'DELETE FROM login_tokens WHERE token = :token';
				$query = $pdo->prepare($sql2);
				$query->bindValue(':token', $token, \PDO::PARAM_STR);
				$query->execute();
				setcookie("LOGINTOKEN", 0, 1, "/");
				echo '{ "Status": "Success" }';
				http_response_code(200);
			} else {
				echo '{ "Error": "Invalid token" }';
				http_response_code(400);
			}
		} else {
			echo '{ "Error": "Malformed request" }';
			http_response_code(400);
		}
	}
	public function Signup()
	{
		global $pdo;
		$password = $_POST['password'];
		$cpassword = $_POST['cpassword'];
		$first_name = $_POST['first_name'];
		$last_name = $_POST['last_name'];
		$username = $first_name . $last_name;
		$email = $_POST['email'];
		if (!empty($username) && !empty($password) && !empty($cpassword) && !empty($first_name) && !empty($last_name) && !empty($email)) {
			if ($password == $cpassword) {
				$password = password_hash($password, PASSWORD_DEFAULT);
				$username = preg_replace('/[^A-Za-z0-9\-]/', '', $username);
				$first_name = preg_replace('/[^A-Za-z0-9\-]/', '', $first_name);
				$last_name = preg_replace('/[^A-Za-z0-9\-]/', '', $last_name);
				$sql = "SELECT * FROM users WHERE email = :email";
				$query = $pdo->prepare($sql);
				$query->bindValue(':email', $email, \PDO::PARAM_STR);
				$query->execute();
				$result = $query->fetch();
				if ($result->email == "") {
					$sql2 = "SELECT username FROM users WHERE username = :username";
					$query2 = $pdo->prepare($sql2);
					$query2->bindValue(':username', $username, \PDO::PARAM_STR);
					$query2->execute();
					$result2 = $query2->fetch();
					if ($result2->username == "") {
						$sql3 = "INSERT INTO users (`username`, `first_name`, `last_name`, `email`, `password`) VALUES (:username , :firstname , :lastname , :email , :password)";
						$query3 = $pdo->prepare($sql3);
						$query3->bindValue(':username', $username, \PDO::PARAM_STR);
						$query3->bindValue(':firstname', $first_name, \PDO::PARAM_STR);
						$query3->bindValue(':lastname', $last_name, \PDO::PARAM_STR);
						$query3->bindValue(':email', $email, \PDO::PARAM_STR);
						$query3->bindValue(':password', $password, \PDO::PARAM_STR);
						$query3->execute();
						$latestID = $pdo->lastInsertId();
						\Helpers::addAuditLog("ACCOUNT_CREATED::{$_SERVER['REMOTE_ADDR']} Created Account {$username} With ID {$latestID}");
						echo "Account Created.";
					} else {
						echo "Username Already Used.";
					}
				} else {
					echo "Email Already Used.";
				}
			} else {
				echo "Passwords Must Match.";
			}
		} else {
			echo "All Fields Are Required To Sign Up.";
		}
	}
	public function Check()
	{
		global $pdo;

		if (isset($_COOKIE['LOGINTOKEN'])) {
			$token = sha1($_COOKIE['LOGINTOKEN']);
			$sql = "SELECT * FROM login_tokens WHERE token = :token";
			$query = $pdo->prepare($sql);
			$query->bindValue(':token', $token, \PDO::PARAM_STR);
			$query->execute();
			$result = $query->fetch();
			if ($result) {
				echo true;
			}
		} else {
			\Helpers::addAuditLog("AUTHENTICATION_FAILED::{$_SERVER['REMOTE_ADDR']} Triggered An Unauthenticated Response In `CheckLogin`");
			echo false;
		}
	}

}