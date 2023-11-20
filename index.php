<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

$pageTitle = "TDF Viewer";
$color1 = "#228B22";
$color2 = "#20B2AA";
$user_data = 'user_data.json';
if(file_exists($user_data))
{
	$jsonString = json_decode(file_get_contents($user_data), true);
	if (array_key_exists("color1",$jsonString))
	{
		$color1 = $jsonString['color1'];
		$color2 = $jsonString['color2'];
	}
}
class Player
{
	public $wins;
	public $losses;
	public $ties;
	public $points;
	public $opponents;
	
	public $name;
	
	public $resistance;
	public $oppresistance;
	public $oppoppresistance;
	
	public $games;
	
	public $dropped;
	
	function __construct()
	{
		$this->wins = 0;
		$this->losses = 0;
		$this->ties = 0;
		$this->dropped = false;
		$this->points = 0;
		$this->opponents = array();
		$this->resistance = array();
		
		$this->games = array();
		
		$this->oppresistance = array();
		$this->oppoppresistance = array();
	}
	
	
	function Record($round)
	{
		return $this->wins.'/'.$this->losses.'/'.$this->ties;
	}
	
	function Points($round)
	{
		return $this->points;
	}
	
	function AddMatch($points, $opp)
	{
		$this->points += $points;
		$this->opponents[] = $opp;
		$this->games[] = $points;
		$res = 0;
		$count = 0;
		for($k = 0; $k < sizeof($this->opponents); $k++)
		{
			if(strcmp($this->opponents[$k], "BYE") != 0)
			{
				if($this->games[$k] == 3)
					$res += 1;
				if($this->games[$k] == 1)
					$res += 0.5;
				$count++;
			}
		}
		if($count > 0)
		{
			$res = $res / $count;
			if($res < 0.25)
				$res = 0.25;
			$this->resistance[] = $res;
		}
		else
		{
			$this->resistance[] = 1;
		}
	}
	
	function GetOORes($r)
	{
		if(sizeof($this->oppoppresistance) > $r)
		{
			return $this->oppoppresistance[$r];
		}
		return $this->oppoppresistance[sizeof($this->oppoppresistance)-1];
	}
	function GetORes($r)
	{
		if(sizeof($this->oppresistance) > $r)
		{
			return $this->oppresistance[$r];
		}
		return $this->oppresistance[sizeof($this->oppresistance)-1];
	}
	
	function ComputeOppResistance($players, $round)
	{
		$this->oppresistance[$round] = 1.0;
		if($round - 1 > 0)
		{
			$this->oppresistance[$round] = $this->oppresistance[$round-1];
		}
		if(sizeof($this->opponents) < $round+1)
		{
			$this->dropped = true;
		}
		if($this->dropped)
			return;
		$nbOpp = 0;
		$res = 0;
		for($r = 0; $r < $round+1 && $r < sizeof($this->opponents); $r++)
		{
			if(strcmp($this->opponents[$r], "BYE") == 0 || strcmp($this->opponents[$r], "LATE") == 0)
			{

			}
			else
			{
				$nbOpp++;
				$res += $players[$this->opponents[$r]]->resistance[sizeof($players[$this->opponents[$r]]->opponents)-1];
			}
			
		}
		if($nbOpp > 0)
		{
			$tmp = $res / $nbOpp;
			if($tmp < 0.25)
				$tmp = 0.25;
			$this->oppresistance[$round] = $tmp;
		}
	}
	
	function ComputeOppOppResistance($players, $round)
	{
		$this->oppoppresistance[$round] = 1.0;
		$nbOpp = 0;
		$res = 0;
		
		for($r = 0; $r < $round+1 && $r < sizeof($this->opponents); $r++)
		{
			if(strcmp($this->opponents[$r], "BYE") == 0 || strcmp($this->opponents[$r], "LATE") == 0)
			{

			}
			else
			{
				$nbOpp++;
				
				$res += $players[$this->opponents[$r]]->oppresistance[sizeof($players[$this->opponents[$r]]->opponents)-1];
			}
			
		}
		if($nbOpp > 0)
		{
			$tmp = $res / $nbOpp;
			
			if($tmp < 0.25)
				$tmp = 0.25;
			$this->oppoppresistance[$round] = $tmp;
		}
	}
}

if(isset($_GET['folder']))
{
	$_POST['folder'] = $_GET['folder'];
	$_POST['current'] = 'qrcode';
}

if(isset($_POST['delete']))
{
	rename($_POST['delete'], 'DELETED_'.date("YmdHis").'_'.$_POST['delete']);
	unset($_POST['folder']);
}
$htmlData = '';
$scriptData = '';
if(isset($_POST['admin']))
{
	$password_path = 'password.json';
	if(isset($_POST["logout"]))
	{
		session_unset();
		session_destroy();
	}

	if(isset($_POST["newpassword"]))
	{
		$jsonData = array();
		$jsonData['hash'] = password_hash($_POST["newpassword"], PASSWORD_DEFAULT);
		$jsonString = json_encode($jsonData, JSON_PRETTY_PRINT);
		$fp = fopen($password_path, 'w');
		fwrite($fp, $jsonString);
		fclose($fp);
		chmod($password_path, 0700);
	}
	$jsonhash = '';
	if(file_exists($password_path))
	{
		$jsonString = json_decode(file_get_contents($password_path), false);
		$jsonhash = $jsonString->hash;
	}

	if($jsonhash === '')
	{
		$htmlData .= '<form action="" method="post" id="create_password_form" class="adminforms">';
		$htmlData .= '<p>';
		$htmlData .= '<h1>Please create a password:</h1><input name="newpassword" autocomplete="current-password" type="password" minlength="6">';
		$htmlData .= '<input name="admin" value="" hidden>';
		$htmlData .= '<p><button class="pushable" type="submit"><span class="front">Create</span></button></p>';
		$htmlData .= '</form>';
	}
	else
	{
		if(isset($_POST["passwordold"]) && isset($_POST["passwordnew1"]) && isset($_POST["passwordnew2"]))
		{
			$old = $_POST["passwordold"];
			$new1 = $_POST["passwordnew1"];
			$new2 = $_POST["passwordnew2"];
			$issue = False;
			if(!password_verify($old, $jsonhash))
			{
				$htmlData .= "Wrong password</html>";
				$issue = True;
			}
			else
			{
				if(strlen($new1) < 6 || strlen($new2) < 6 || strlen($new1) != strlen($new2) || strcmp($new1, $new2) != 0)
				{
					$htmlData .= "Issue with new passwords</html>";
					$issue = True;
				}
			}
			if(!$issue)
			{
				$jsonData = array();
				$jsonData['hash'] = password_hash($new2, PASSWORD_DEFAULT);
				$jsonString = json_encode($jsonData, JSON_PRETTY_PRINT);
				$fp = fopen($password_path, 'w');
				fwrite($fp, $jsonString);
				fclose($fp);
				chmod($password_path, 0700);
				$htmlData .= "Password has been changed</html>";
				session_unset();
				session_destroy();
			}
		}

		$password = "";
		if(isset($_POST["password"]))
		{
			$password = $_POST["password"];
			if(!password_verify($password, $jsonhash))
			{
				$htmlData .= 'Wrong password!';
			}
			else
			{
				$_SESSION["password"] = $password;
				$_SESSION["league"] = explode('/', getcwd())[sizeof(explode('/', getcwd()))-1];
			}
		}
		else
		{
			if(!isset($_SESSION["password"]))
			{
				$htmlData .= '<form action="" method="post" id="password_form" class="adminforms">';
				$htmlData .= '<p>';
				$htmlData .= '<h1>Password:</h1><input name="password" autocomplete="current-password" type="password">';
				$htmlData .= '<input name="admin" value="" hidden>';
				$htmlData .= '<p><button class="pushable" type="submit"><span class="front">Login</span></button></p>';
				$htmlData .= '</form>';
			}
			else
			{
				$password = $_SESSION["password"];
			}
		}

		if(!password_verify($password, $jsonhash))
		{
			if(session_status() == PHP_SESSION_ACTIVE)
			{
				session_unset();
				session_destroy();
			}
		}
		else
		{
			$htmlData .= '<form method="post" enctype="multipart/form-data" id="logo_form" class="adminforms">';
			$htmlData .= '<h1>Upload your logo:</h1>';
			$htmlData .= '<input name="admin" value="" hidden>';
			$htmlData .= '<input name="upload" value="" hidden>';
			$htmlData .= '<input id="logo" name="logo" value="logo" hidden>';
			$htmlData .= 'Select the League Logo (PNG) to upload:';
			$htmlData .= '<input type="file" name="fileToUpload" id="fileToUpload" class="button" style="width:300px">';
			$htmlData .= '<button class="pushable" type="submit"><span class="front">Upload file</span></button></p>';
			$htmlData .= '</form>';
			
			$htmlData .= '<form action="" method="post" id="changepwd_form" class="adminforms">';
			$htmlData .= '<h1>Change your password:</h1>';
			$htmlData .= '<input name="admin" value="" hidden>';
			$htmlData .= '<label for="pass1">Old Password :</label><input type="password" id="pass1" name="passwordold" required><p>';
			$htmlData .= '<label for="pass2">New Password (6 characters minimum):</label><input type="password" id="pass2" name="passwordnew1" minlength="6" required><p>';
			$htmlData .= '<label for="pass3">New Password (6 characters minimum):</label><input type="password" id="pass3" name="passwordnew2" minlength="6" required><p>';
			$htmlData .= '<p><button class="pushable" type="submit"><span class="front">Change Password</span></button></p>';
			$htmlData .= '</form>';
			
			$lname = '';
			if(file_exists($user_data))
			{
				$jsonData = json_decode(file_get_contents($user_data), true);
				if (array_key_exists("leaguename",$jsonData))
				{
					$lname = $jsonData['leaguename'];
				}
			}
			
			$htmlData .= '<form action="" method="post" id="name_form" class="adminforms">';
			$htmlData .= '<input name="admin" value="" hidden>';
			$htmlData .= '<h1>League/Shop Name:</h1>';
			$htmlData .= '<input id="leaguename" name="leaguename" value="'.$lname.'"><p>';
			$htmlData .= '<p><button class="pushable" type="submit"><span class="front">Save Name</span></button></p>';
			$htmlData .= '</form>';
			
			$resistances = False;
			if(isset($_POST['resistances']))
			{
				if(file_exists($user_data))
				{
					$jsonData = json_decode(file_get_contents($user_data), true);
				}
				else
				{
					$jsonData = array();
				}
				$jsonData["resistances"] = 'No';
				if($_POST['resistances'] == 'Yes')
				{
					$jsonData["resistances"] = 'Yes';
					$resistances = True;
				}
				$fp = fopen($user_data, 'w');
				fwrite($fp, json_encode($jsonData, JSON_PRETTY_PRINT));
				fclose($fp);
			}
			else
			{
				if(isset($_POST['hidden_res']))
				{
					if(file_exists($user_data))
					{
						$jsonData = json_decode(file_get_contents($user_data), true);
					}
					else
					{
						$jsonData = array();
					}
					$jsonData["resistances"] = 'No';
					$fp = fopen($user_data, 'w');
					fwrite($fp, json_encode($jsonData, JSON_PRETTY_PRINT));
					fclose($fp);
				}
				else
				{
					if(file_exists($user_data))
					{
						$jsonData = json_decode(file_get_contents($user_data), true);
						if (array_key_exists("resistances",$jsonData))
						{
							if($jsonData['resistances'] == 'Yes')
							{
								$resistances = True;
							}
						}
					}
				}
			}
			
			$roundstanding = False;
			if(isset($_POST['roundstanding']))
			{
				if(file_exists($path))
				{
					$jsonData = json_decode(file_get_contents($path), true);
				}
				else
				{
					$jsonData = array();
				}
				$jsonData["roundstanding"] = 'No';
				if($_POST['roundstanding'] == 'Yes')
				{
					$jsonData["roundstanding"] = 'Yes';
					$roundstanding = True;
				}
				$fp = fopen($user_data, 'w');
				fwrite($fp, json_encode($jsonData, JSON_PRETTY_PRINT));
				fclose($fp);
			}
			else
			{
				if(isset($_POST['hidden_res']))
				{
					if(file_exists($user_data))
					{
						$jsonData = json_decode(file_get_contents($user_data), true);
					}
					else
					{
						$jsonData = array();
					}
					$jsonData["roundstanding"] = 'No';
					$fp = fopen($user_data, 'w');
					fwrite($fp, json_encode($jsonData, JSON_PRETTY_PRINT));
					fclose($fp);
				}
				else
				{
					if(file_exists($user_data))
					{
						$jsonData = json_decode(file_get_contents($user_data), true);
						if (array_key_exists("roundstanding",$jsonData))
						{
							if($jsonData['roundstanding'] == 'Yes')
							{
								$roundstanding = True;
							}
						}
					}
				}
			}
			
			$htmlData .= '<form action="" method="post" id="resistances_form" class="adminforms">';
			$htmlData .= '<input name="admin" value="" hidden>';
			$htmlData .= "<h1>Added Data:</h1>";
			$htmlData .= '<input type="hidden" id="hidden_res" name="hidden_res" value="Yes">';
			$htmlData .= '<input type="checkbox" id="resistances" name="resistances" value="Yes"';
			if($resistances == True)
			{
				$htmlData .= ' checked';
			}
			$htmlData .= '><label for="resistances">Show resistances</label><br>';
			
			$htmlData .= '<input type="checkbox" id="roundstanding" name="roundstanding" value="Yes"';
			if($roundstanding == True)
			{
				$htmlData .= ' checked';
			}
			$htmlData .= '><label for="roundstanding">Show rounds\' standings</label><br>';
			$htmlData .= '<p><button class="pushable" type="submit"><span class="front">Save Added Data</span></button></p>';
			$htmlData .= '</form>';
			
			if(isset($_POST['leaguename']))
			{
				if(file_exists($user_data))
				{
					$jsonData = json_decode(file_get_contents($user_data), true);
				}
				else
				{
					$jsonData = array();
				}
				$jsonData["leaguename"] = $_POST['leaguename'];
				$fp = fopen($user_data, 'w');
				fwrite($fp, json_encode($jsonData, JSON_PRETTY_PRINT));
				fclose($fp);
			}
			
			$htmlData .= '<form action="" method="post" id="changecolor_form" class="adminforms">';
			$htmlData .= '<input name="admin" value="" hidden>';
			$htmlData .= '<h1>Theme:</h1>';
			if(isset($_POST['color']))
			{
				$colors = explode("|", $_POST['color']);
				if(file_exists($user_data))
				{
					$jsonData = json_decode(file_get_contents($user_data), true);
				}
				else
				{
					$jsonData = array();
				}
				$jsonData["color1"] = $colors[0];
				$jsonData["color2"] = $colors[1];
				$fp = fopen($user_data, 'w');
				fwrite($fp, json_encode($jsonData, JSON_PRETTY_PRINT));
				fclose($fp);
			}
			$htmlData .= '<p><button class="pushable" type="submit" name="color" style="background:#228B22;" value="#228B22|#20B2AA"><span class="front" style="background:#20B2AA;"></span></button></p>';
			$htmlData .= '<p><button class="pushable" type="submit" name="color" style="background:#c1d5f8;" value="#c1d5f8|#6495ED"><span class="front" style="background:#6495ED;"></span></button></p>';
			$htmlData .= '<p><button class="pushable" type="submit" name="color" style="background:#cf8ceb;" value="#cf8ceb|#9400D3"><span class="front" style="background:#9400D3;"></span></button></p>';
			$htmlData .= '<p><button class="pushable" type="submit" name="color" style="background:#ffbea6;" value="#ffbea6|#FF4500"><span class="front" style="background:#FF4500;"></span></button></p>';
			$htmlData .= '<p><button class="pushable" type="submit" name="color" style="background:#a6d3d3;" value="#a6d3d3|#008080"><span class="front" style="background:#008080;"></span></button></p>';
			$htmlData .= '<p><button class="pushable" type="submit" name="color" style="background:#d1b5a1;" value="#d1b5a1|#8B4513"><span class="front" style="background:#8B4513;"></span></button></p>';
			$htmlData .= '</form>';
			
			$htmlData .= '<form action="" method="post" id="logout_form">';
			$htmlData .= '<input name="admin" value="" hidden>';
			$htmlData .= '<input id="logout" name="logout" value="logout" hidden>';
			$htmlData .= '<p><button class="pushable" type="submit"><span class="front">Logout</span></button></p>';
			$htmlData .= '</form>';
		}
	}
}
if(isset($_POST['upload']))
{
	if(!(isset($_SESSION["password"]) && isset($_SESSION["league"]) && $_SESSION["league"] == explode('/', getcwd())[sizeof(explode('/', getcwd()))-1]))
	{
		$htmlData .= '<h1>Please login in the Administration panel!</h1>';
	}
	else
	{
		$uploadOk = 1;
		if(isset($_POST['tournament']))
		{
			if($_POST['tournament'] != '-- Select tournament --' && strlen($_POST['tournament']) > 0)
			{
				$target_file = './'.htmlspecialchars($_POST['tournament']).'/'.time().".tdf";
				setcookie("current", $_POST['tournament']);
			}
			else
			{
				$htmlData .= "Sorry, your file was not uploaded, select a folder";
				setcookie("current", '');
				$uploadOk = 0;
			}
		}
		if(isset($_POST['logo']))
		{
			$extension = strtolower(pathinfo($_FILES["fileToUpload"]["name"],PATHINFO_EXTENSION));
			if(strtolower($extension) == "png")
			{
				//$target_file = './logo.'.$extension;
				$target_file = './logo.png';
			}
			else
			{
				$uploadOk = 0;
			}
		}
		
		if((isset($_POST['tournament']) &&  $_POST['tournament'] != '-- Select tournament --' && strlen($_POST['tournament']) > 0) || isset($_POST['logo']))
		{
			if (isset($_POST['tournament']) && strtolower(pathinfo($_FILES["fileToUpload"]["name"],PATHINFO_EXTENSION)) != "tdf")
			{
				$htmlData .= "Sorry, wrong file type!";
				$uploadOk = 0;
			}

			// Check file size
			if ($_FILES["fileToUpload"]["size"] > 500000)
			{
				$htmlData .= "Sorry, your file is too large.";
				$uploadOk = 0;
			}

			// Check if $uploadOk is set to 0 by an error
			if ($uploadOk == 0)
			{
				$htmlData .= "Sorry, your file was not uploaded.";
				// if everything is ok, try to upload file
			} 
			else
			{
				if(move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file))
				{
					$htmlData .= "File uploaded to ".$target_file;
				}
			}
		}
		
		$currentDir = "";
		if(isset($_COOKIE["current"]))
			$currentDir = $_COOKIE["current"];
		
		$htmlData .= '<form method="post" enctype="multipart/form-data">';
			$htmlData .= '<input type="text" name="upload" hidden>';
			$htmlData .= 'Tournament : <select id="tournament" name="tournament"><option value="">-- Select tournament --</option>';
			$Mydir = './';
			foreach(glob($Mydir.'*', GLOB_ONLYDIR) as $dir)
			{
				$dir = str_replace($Mydir, '', $dir);
				
				if(strpos($dir, 'DELETED_') !== 0)
				{
					$files = scandir($dir);
					
					if(sizeof($files) > 2)
					{
						$target_file = './'.$dir.'/'.$files[sizeof($files) - 1];
						$myXMLData = file_get_contents($target_file);
						$xml=simplexml_load_string($myXMLData) or exit();//die("Error: Cannot create object");

						if(!isset($xml->standings))
						{
							if(strcmp($dir, $currentDir) == 0)
							{
								$htmlData .= '<option class="first" value="'.$dir.'" selected>'.$dir.'</option>';
							}
							else
								$htmlData .= '<option class="first" value="'.$dir.'">'.$dir.'</option>';
						}
						else
						{
							$htmlData .= '<option class="hidden" value="'.$dir.'" hidden="hidden">'.$dir.'</option>';
						}
					}
					else
					{
						if(strcmp($dir, $currentDir) == 0)
						{
							$htmlData .= '<option class="first" value="'.$dir.'" selected>'.$dir.'</option>';
						}
						else
							$htmlData .= '<option class="first" value="'.$dir.'">'.$dir.'</option>';
					}
				}
			}
			$htmlData .= '</select><br><br>';
			$htmlData .= 'Select tdf to upload:';
			$htmlData .= '<input type="file" name="fileToUpload" id="fileToUpload" class="button" style="width:300px">';
		$htmlData .= '<button class="pushable" type="submit"><span class="front">Upload file</span></button>';
		$htmlData .= '</form>';
		$htmlData .= '<button class="pushable" type="submit" onclick="toggleHidden()"><span class="front" id="toggle">Show finished</span></button>';
	}
}
if(isset($_POST['add']))
{
	if(!(isset($_SESSION["password"]) && isset($_SESSION["league"]) && $_SESSION["league"] == explode('/', getcwd())[sizeof(explode('/', getcwd()))-1]))
	{
		$htmlData .= '<body><h1>Please login in the Administration panel!</h1></body>';

	}
	else
	{
		if(isset($_POST['foldername']) && $_POST['foldername'])
		{
			$foldername = $_POST['foldername'];
			$foldername = str_replace('/', '_slash_', $foldername);
			$structure = getcwd().DIRECTORY_SEPARATOR.$foldername;
			if (!mkdir($structure, 0700, true))
			{
				die('Failed to create folders...');
			}
			else
			{
				$htmlData .= $foldername.' Created';
			}
		}
		else
		{
			$htmlData .= '<form method="post">';
			$htmlData .= '<input type="text" name="add" hidden>';
			$htmlData .= '<input type="text" name="foldername">';
			$htmlData .= '<button class="pushable" type="submit"><span class="front">Create Tournament</span></button>';
			$htmlData .= '</form>';
		}
	}
}
if(isset($_POST['current']) || isset($_POST['archives']))
{
	if(!isset($_POST['folder']))
	{
		if(isset($_POST['archives']))
		{
			$pageTitle = "Archives";
			$htmlData .= '<h1>Archived Tournaments :</h1>';
		}
		else
		{
			$pageTitle = "Current Tournaments";
			$htmlData .= '<h1>Current Tournaments :</h1>';
		}
		
		
		$Mydir = './';
		foreach(glob($Mydir.'*', GLOB_ONLYDIR) as $dir)
		{
			$dir = str_replace($Mydir, '', $dir);
			$unprotectedName = str_replace('_slash_', '/', $dir);
			$files = scandir($dir);
			if(strpos($dir, 'DELETED_') !== 0)
			{
				if(sizeof($files)>2)
				{
					$target_file = './'.$dir.'/'.$files[sizeof($files) - 1];
					$myXMLData = file_get_contents($target_file);
					$xml=simplexml_load_string($myXMLData) or exit();//die("Error: Cannot create object");
					if(isset($xml->pods->pod) && isset($xml->pods->pod[0]->rounds))
					{
					    $xmlDate = explode(' ', $xml->pods->pod[0]->rounds->round[0]->matches->match[0]->timestamp)[0];
						$startdate = DateTime::createFromFormat("m/d/Y", $xmlDate);
					}
					else
					{$startdate = new DateTime();}
					$now = new DateTime();
					$difference = date_diff($startdate, $now);
					if($difference->days <= 3 && !isset($_POST['archives']))
					{
						$htmlData .= '<form action="" method="post" id="openTournament">';
						$htmlData .=  '<input id="current" name="current" hidden>';
						$htmlData .=  '<input id="folder" name="folder" value="'.$dir.'" hidden>';
						$htmlData .=  '<p><button class="pushable" type="submit"><span class="front">'.$unprotectedName.'</span></button></p>';
						$htmlData .=  '</form>';
					}
					if($difference->days > 3 && isset($_POST['archives']))
					{
						$htmlData .=  '<form action="" method="post" id="openTournament">';
						$htmlData .=  '<input id="current" name="current" hidden>';
						$htmlData .=  '<input id="folder" name="folder" value="'.$dir.'" hidden>';
						$htmlData .=  '<p><button class="pushable" type="submit"><span class="front">'.$unprotectedName.'</span></button></p>';
						$htmlData .=  '</form>';
					}
				}
				else
				{
					if(!isset($_POST['archives']))
					{
						$htmlData .=  '<form action="" method="post" id="openTournament">';
						$htmlData .=  '<input id="current" name="current" hidden>';
						$htmlData .=  '<input id="folder" name="folder" value="'.$dir.'" hidden>';
						$htmlData .=  '<p><button class="pushable" type="submit"><span class="front">'.$unprotectedName.'</span></button></p>';
						$htmlData .=  '</form>';
					}
				}
			}
		}
	}
	else
	{
		$dir = $_POST['folder'];
		
		
		$hiddenStream = '';
		if(!(isset($_SESSION["password"]) && isset($_SESSION["league"]) && $_SESSION["league"] == explode('/', getcwd())[sizeof(explode('/', getcwd()))-1]))
		{
			if(file_exists($user_data))
			{
				$jsonString = json_decode(file_get_contents($user_data), true);
				if (array_key_exists($dir."URL",$jsonString))
				{
					if(strlen($jsonString[$dir."URL"]) > 0)
					{
						$hiddenStream .= '<button class="pushable" onclick=" window.open(\''.$jsonString[$dir."URL"].'\', \'_blank\'); return false;"><span class="front">Stream</span></button>';
					}
				}
			}
		}
		else
		{
			if(isset($_POST['streamlink']))
			{
				if(file_exists($user_data))
				{
					$jsonData = json_decode(file_get_contents($user_data), true);
				}
				else
				{
					$jsonData = array();
				}
				$jsonData[$dir."URL"] = $_POST['streamlink'];
				$fp = fopen($user_data, 'w');
				fwrite($fp, json_encode($jsonData, JSON_PRETTY_PRINT));
				fclose($fp);
			}
			
			$streamURL = '';
			if(file_exists($user_data))
			{
				$jsonString = json_decode(file_get_contents($user_data), true);
				if (array_key_exists($dir."URL",$jsonString))
				{
					$streamURL = $jsonString[$dir."URL"];
				}
			}
			
			$hiddenStream .= '<form action="" method="post" id="add_stream_link_form" class="adminforms">';
			$hiddenStream .= '<h1>Add a stream link:</h1><input name="streamlink" value="'.$streamURL.'">';
			$hiddenStream .= '<input name="current" value="" hidden>';
			$hiddenStream .= '<input name="folder" value="'.$dir.'" hidden>';
			$hiddenStream .= '<p><button class="pushable" type="submit"><span class="front">Add</span></button></p>';
			$hiddenStream .= '</form>';
		}
		
		$dir = str_replace('_space_', ' ', $dir);
		$dir = str_replace("\ ", "%20", $dir);
		$unprotectedName = str_replace('_slash_', '/', $dir);
		
		$files = scandir($dir);
		if(sizeof($files) > 2)
		{
			$target_file = './'.$dir.'/'.$files[sizeof($files) - 1];
			$url = 'https://'.htmlspecialchars($_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/?folder='.str_replace(' ', '_space_', $dir));
			
			
			
			//XML/TDF Loading
			$myXMLData = file_get_contents($target_file);
			$xml=simplexml_load_string($myXMLData) or exit();//die("Error: Cannot create object");
			
			$nbPods = sizeof($xml->pods->pod);
			
			$shopName = '';
			$roundstanding = False;
			$resistances = False;
			if(file_exists($user_data))
			{
				$jsonData = json_decode(file_get_contents($user_data), true);
				if (array_key_exists("leaguename",$jsonData))
				{
					$shopName = $jsonData['leaguename'];
				}
				if (array_key_exists("roundstanding",$jsonData))
				{
					if($jsonData['roundstanding'] == 'Yes')
					{
						$roundstanding = True;
					}
				}
				if (array_key_exists("resistances",$jsonData))
				{
					if($jsonData['resistances'] == 'Yes')
					{
						$resistances = True;
					}
				}
			}
			$pageTitle = $unprotectedName;
			$tournamentTitle = $xml->data->name;
			
			$scriptData .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>';
			$htmlData .= '<div id="main">';
			
			$htmlData .= '<div class="header">';
			$htmlData .= '<div class="column tournament" id="pdfToHide"><button class="pushable trmnt" onclick="showImage()"><span class="front">'.$unprotectedName.'</span></button><img id="image" src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl='.$url.'&choe=UTF-8" title="Link to this tournament" style="display: none;margin-left: auto;margin-right: auto;width: 50%;"/>';
			$htmlData .= '<div id="tohide" hidden>';
			$htmlData .= '<a href="'.$url.'">Lien</a><br>';
			$htmlData .= '<button id="pdfbutton" onclick="toPDF()"></button>';
			$htmlData .= $hiddenStream;
			$htmlData .= '</div>';
			$htmlData .= '</div>';
			$htmlData .= '<div class="column league"><div class="tTitle">'.$tournamentTitle.'</div><div>----</div><div class="sName">'.$shopName.'</div></div>';
			$htmlData .= '<div class="column logo"><img src="logo.png" alt="Logo" width="250px"></div>';
			$htmlData .= '</div>';
			if((isset($_SESSION["password"]) && isset($_SESSION["league"]) && $_SESSION["league"] == explode('/', getcwd())[sizeof(explode('/', getcwd()))-1]))
			{
				$htmlData .= '<form action="" method="post" id="deleteTournament">';
				$htmlData .= '<input id="delete" name="delete" value="'.$dir.'" hidden>';
				$htmlData .= '<p><button class="pushable" type="submit" onclick="if(confirm(\'Are you sure?\')){if(confirm(\'Are you sure?\'))return true;else return false}return false;"><span class="front">Delete Tournament</span></button></p>';
				$htmlData .= '</form>';
			}
			
			$definedPods = array();
			//Create Tabs
			$htmlData .= '<div class="tab">';
				$htmlData .= '<button class="tablinks" onclick="openTab(event, \'Players\')" id="defaultOpen">Players</button>';
				for($pod = 0; $pod < $nbPods; $pod++)
				{
					if($xml->pods->pod[$pod]["category"] == 0)
						$podName = 'Juniors';
					if($xml->pods->pod[$pod]["category"] == 1)
						$podName = 'Seniors';
					if($xml->pods->pod[$pod]["category"] == 2)
						$podName = 'Masters';
					if($xml->pods->pod[$pod]["category"] == 9)
						$podName = 'Seniors & Masters';
					if($xml->pods->pod[$pod]["category"] == 8)
						$podName = 'Juniors & Seniors';
					if($xml->pods->pod[$pod]["category"] == 10)
						$podName = 'Juniors & Seniors & Masters';
					$definedPods[] = $xml->pods->pod[$pod]["category"];
					$htmlData .= '<button class="tablinks" onclick="openTab(event, \'P'.$pod.'\')">'.$podName.'</button>';
				}
			$htmlData .= '</div>';

			$htmlData .= '<div id="Data" class="tabcontent">';
				$htmlData .= 'Name : '.$xml->data->name.'<br>';
				$htmlData .= 'ID : '.$xml->data->id.'<br>';
				$htmlData .= 'City : '.$xml->data->city.'<br>';
				$htmlData .= 'State : '.$xml->data->state.'<br>';
				$htmlData .= 'Country : '.$xml->data->country.'<br>';
				$htmlData .= 'Round time : '.$xml->data->roundtime.'<br>';
				$htmlData .= 'Finals Round Time : '.$xml->data->finalsroundtime.'<br>';
				$htmlData .= 'Organizer : '.$xml->data->organizer["popid"].' - '.$xml->data->organizer["name"].'<br>';
				$htmlData .= 'Start Date : '.$xml->data->startdate.'<br>';
			$htmlData .= '</div>';

			$players = array();
			$playersFirstnames = array();
			$playersLastnames = array();

			$htmlData .= '<div id="Players" class="tabcontent">';
				$htmlData .= '<table>';
				$htmlData .= '<tr>';
					//$htmlData .= '<th>Id</th>';
					$htmlData .= '<th>Name</th>';
					$htmlData .= '<th>Surname</th>';
				$htmlData .= '</tr>';
				$nbPlayers = sizeof($xml->players->player);
				for($p = 0; $p < $nbPlayers; $p++)
				{
					$htmlData .= '<tr>';
					/*$htmlData .= '<td>';
					$htmlData .= $xml->players->player[$p]['userid'];
					$htmlData .= '</td>';*/
					$htmlData .= '<td>';
					//if(intval(explode('/', $xml->players->player[$p]->birthdate)[sizeof(explode('/', $xml->players->player[$p]->birthdate))-1]) > 2007)
					//{
						$htmlData .= $xml->players->player[$p]->lastname;
					//}
					//else
					//{
						//$htmlData .= $xml->players->player[$p]->lastname[0];
					//}
					$htmlData .= '</td>';
					$htmlData .= '<td>';
					$htmlData .= $xml->players->player[$p]->firstname;
					$htmlData .= '</td>';
					$htmlData .= '</tr>';
					
					$key = $xml->players->player[$p]['userid'];
					
					$playersFirstnames[strval($key)] = $xml->players->player[$p]->firstname;
					//if(intval(explode('/', $xml->players->player[$p]->birthdate)[sizeof(explode('/', $xml->players->player[$p]->birthdate))-1]) > 2007)
					//{
						$playersLastnames[strval($key)] = $xml->players->player[$p]->lastname;
					//}
					//else
					//{
					//	$playersLastnames[strval($key)] = $xml->players->player[$p]->lastname[0];
					//}
					
					$players[strval($key)] = new Player();
					$players[strval($key)]->name = $playersFirstnames[strval($key)].' '.$playersLastnames[strval($key)];
				}
				$htmlData .= '</table>';
			$htmlData .= '</div>';
			
			$startTopCut = 999;
			
			for($pod = 0; $pod < $nbPods; $pod++)
			{
				$htmlData .= '<div id="P'.$pod.'" class="tabcontent">';
				$nbTopCutPlayers = 0;
				$topCutLevel = 0;
				$nbRounds = sizeof($xml->pods->pod[$pod]->rounds->round);
				$htmlData .= '<div class="tab">';
				for($r = 1; $r <= $nbRounds; $r++)
				{
					$htmlData .= '<button class="';
					if($xml->pods->pod[$pod]->rounds->round[$r-1]['type'] == 1)
					{
						$htmlData .= 'topcut ';
					}
					$htmlData .= 'tablinks subtablinks" onclick="subopenTab(event, \'R'.$pod.'_'.$r.'\')"';
					if($r == $nbRounds)
					{
						$htmlData .= ' id="defaultOpenP'.$pod.'"';
					}
					$htmlData .= '>Round'.$r.'</button>';
				}
				$htmlData .= '<button class="tablinks subtablinks" onclick="subopenTab(event, \'S'.$pod.'\')">Standings</button>';
				$htmlData .= '</div>';
					
				$nbPodPlayers = sizeof($xml->pods->pod[$pod]->subgroups->subgroup->players->player);
				$podPlayersID = array();
				for($pl = 0; $pl < $nbPodPlayers; $pl++)
					$podPlayersID[] = strval($xml->pods->pod[$pod]->subgroups->subgroup->players->player[$pl]['userid']);
				
				$nbTCRows = 0;
				$nbTCColumns = 0;
				$topCutStartRound = 0;
				for($r = 1; $r <= $nbRounds; $r++)
				{
					//rounds data
					$round = $r - 1;
					$htmlData .= '<div id="R'.$pod.'_'.$r.'" class="tabcontent subcontent">';
					if($xml->pods->pod[$pod]->rounds->round[$round]['type'] == 1)
					{
						$htmlData .= '<div class="topcut_table"><table>';
						if($nbTopCutPlayers == 0)
						{
							$nbTopCutPlayers = sizeof($xml->pods->pod[$pod]->rounds->round[$round]->matches->match) * 2;
							$nbTCRows = sizeof($xml->pods->pod[$pod]->rounds->round[$round]->matches->match);
							$nbTCColumns = $nbTopCutPlayers - 1;
							$topCutStartRound = $round;
						}
						if($nbTopCutPlayers == 8)
						{
							$htmlData .= '<tr>';
							$htmlData .= '<td class="topcutCell">';
							$htmlData .= '{PLAYER1}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="4" class="link4">';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="2" class="topcutCell">';
							$htmlData .= '{PLAYER18}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="4" class="link2">';
							$htmlData .= '</td>';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="4" class="topcutCell">';
							$htmlData .= '{PLAYER1845}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="4" class="topcutCell winnerCup" style="vertical-align:top">';
							$htmlData .= '{WINNER}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="4" class="topcutCell">';
							$htmlData .= '{PLAYER2367}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="4" class="link2rev">';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="2" class="topcutCell">';
							$htmlData .= '{PLAYER27}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="4" class="link4rev">';
							$htmlData .= '</td>';
							$htmlData .= '<td class="topcutCell">';
							$htmlData .= '{PLAYER2}';
							$htmlData .= '</td>';
							$htmlData .= '</tr>';
							
							$htmlData .= '<tr>';
							$htmlData .= '<td class="topcutCell">';
							$htmlData .= '{PLAYER8}';
							$htmlData .= '</td>';
							
							$htmlData .= '<td class="topcutCell">';
							$htmlData .= '{PLAYER7}';
							$htmlData .= '</td>';
							$htmlData .= '</tr>';
							
							$htmlData .= '<tr>';
							$htmlData .= '<td class="topcutCell">';
							$htmlData .= '{PLAYER4}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="2" class="topcutCell">';
							$htmlData .= '{PLAYER45}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="2" class="topcutCell">';
							$htmlData .= '{PLAYER36}';
							$htmlData .= '</td>';
							$htmlData .= '<td class="topcutCell">';
							$htmlData .= '{PLAYER3}';
							$htmlData .= '</td>';
							$htmlData .= '</tr>';
							
							$htmlData .= '<tr>';
							$htmlData .= '<td class="topcutCell">';
							$htmlData .= '{PLAYER5}';
							$htmlData .= '</td>';
							$htmlData .= '<td class="topcutCell">';
							$htmlData .= '{PLAYER6}';
							$htmlData .= '</td>';
							$htmlData .= '</tr>';
							
							$p1 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[0]->player1['userid']);
							$p2 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[3]->player1['userid']);
							$p3 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[2]->player1['userid']);
							$p4 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[1]->player1['userid']);
							$p5 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[1]->player2['userid']);
							$p6 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[2]->player2['userid']);
							$p7 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[3]->player2['userid']);
							$p8 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[0]->player2['userid']);
							
							$p18 = '';
							$p27 = '';
							$p36 = '';
							$p45 = '';
							$p1845 = '';
							$p2736 = '';
							
							$p1status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[0]['outcome'] == 1)
							{
								$p1status = " topcutWinner";
								$p18 = $p1;
							}
							$p2status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[3]['outcome'] == 1)
							{
								$p2status = " topcutWinner";
								$p27 = $p2;
							}
							$p3status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[2]['outcome'] == 1)
							{
								$p3status = " topcutWinner";
								$p36 = $p3;
							}
							$p4status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[1]['outcome'] == 1)
							{
								$p4status = " topcutWinner";
								$p45 = $p4;
							}
							$p5status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[1]['outcome'] == 2)
							{
								$p5status = " topcutWinner";
								$p45 = $p5;
							}
							$p6status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[2]['outcome'] == 2)
							{
								$p6status = " topcutWinner";
								$p36 = $p6;
							}
							$p7status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[3]['outcome'] == 2)
							{
								$p7status = " topcutWinner";
								$p27 = $p7;
							}
							$p8status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[0]['outcome'] == 2)
							{
								$p8status = " topcutWinner";
								$p18 = $p8;
							}
							
							if($round >= $topCutStartRound+1)
							{
								$p1845status = "";
								if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound+1]->matches->match[0]['outcome'] == 1)
								{
									$p1845 = $p18;
									$p18status = " topcutWinner";
								}
								if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound+1]->matches->match[0]['outcome'] == 2)
								{
									$p1845 = $p45;
									$p45status = " topcutWinner";
								}
								
								$p2736status = "";
								if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound+1]->matches->match[1]['outcome'] == 2)
								{
									$p2736 = $p27;
									$p27status = " topcutWinner";
								}
								if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound+1]->matches->match[1]['outcome'] == 1)
								{
									$p2736 = $p36;
									$p36status = " topcutWinner";
								}
							}
							
							if($round >= $topCutStartRound+2)
							{
								$pWinnerstatus = "";
								if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound+2]->matches->match[0]['outcome'] == 1)
								{
									$pWinner = $p1845;
									$p1845status = " topcutWinner";
									$pWinnerstatus = " tournamentwinner";
								}
								if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound+2]->matches->match[0]['outcome'] == 2)
								{
									$pWinner = $p2736;
									$pWinnerstatus = " tournamentwinner";
									$p2736status = " topcutWinner";
								}							
							}
							
							
							
							$htmlData = str_replace('{PLAYER1}', '<div class="topcutplayer'.$p1status.'">'.$playersFirstnames[$p1].' '.$playersLastnames[$p1].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER2}', '<div class="topcutplayer'.$p2status.'">'.$playersFirstnames[$p2].' '.$playersLastnames[$p2].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER3}', '<div class="topcutplayer'.$p3status.'">'.$playersFirstnames[$p3].' '.$playersLastnames[$p3].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER4}', '<div class="topcutplayer'.$p4status.'">'.$playersFirstnames[$p4].' '.$playersLastnames[$p4].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER5}', '<div class="topcutplayer'.$p5status.'">'.$playersFirstnames[$p5].' '.$playersLastnames[$p5].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER6}', '<div class="topcutplayer'.$p6status.'">'.$playersFirstnames[$p6].' '.$playersLastnames[$p6].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER7}', '<div class="topcutplayer'.$p7status.'">'.$playersFirstnames[$p7].' '.$playersLastnames[$p7].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER8}', '<div class="topcutplayer'.$p8status.'">'.$playersFirstnames[$p8].' '.$playersLastnames[$p8].'</div>', $htmlData);
							
							if(strlen($p18) > 0)
							{	
								$htmlData = str_replace('{PLAYER18}', '<div class="topcutplayer'.$p18status.'">'.$playersFirstnames[$p18].' '.$playersLastnames[$p18].'</div>', $htmlData);
							}
							else
							{
								$htmlData = str_replace('{PLAYER18}', '', $htmlData);
							}
							
							if(strlen($p27) > 0)
							{	
								$htmlData = str_replace('{PLAYER27}', '<div class="topcutplayer'.$p27status.'">'.$playersFirstnames[$p27].' '.$playersLastnames[$p27].'</div>', $htmlData);
							}
							else
							{
								$htmlData = str_replace('{PLAYER27}', '', $htmlData);
							}
							
							if(strlen($p36) > 0)
							{	
								$htmlData = str_replace('{PLAYER36}', '<div class="topcutplayer'.$p36status.'">'.$playersFirstnames[$p36].' '.$playersLastnames[$p36].'</div>', $htmlData);
							}
							else
							{
								$htmlData = str_replace('{PLAYER36}', '', $htmlData);
							}
							
							if(strlen($p45) > 0)
							{	
								$htmlData = str_replace('{PLAYER45}', '<div class="topcutplayer'.$p45status.'">'.$playersFirstnames[$p45].' '.$playersLastnames[$p45].'</div>', $htmlData);
							}
							else
							{
								$htmlData = str_replace('{PLAYER45}', '', $htmlData);
							}
							if(strlen($p1845) > 0)
								$htmlData = str_replace('{PLAYER1845}', '<div class="topcutplayer'.$p1845status.'">'.$playersFirstnames[$p1845].' '.$playersLastnames[$p1845].'</div>', $htmlData);
							else
								$htmlData = str_replace('{PLAYER1845}', '', $htmlData);
							if(strlen($p2736) > 0)
								$htmlData = str_replace('{PLAYER2367}', '<div class="topcutplayer'.$p2736status.'">'.$playersFirstnames[$p2736].' '.$playersLastnames[$p2736].'</div>', $htmlData);
							else
								$htmlData = str_replace('{PLAYER2367}', '', $htmlData);
							if(strlen($pWinner) > 0)
								$htmlData = str_replace('{WINNER}', '<div class="topcutplayer'.$pWinnerstatus.'">'.$playersFirstnames[$pWinner].' '.$playersLastnames[$pWinner].'</div>', $htmlData);
							else
								$htmlData = str_replace('{WINNER}', '', $htmlData);
							
						}
						if($nbTopCutPlayers == 4)
						{
							$htmlData .= '<tr>';
							$htmlData .= '<td class="topcutCell4">';
							$htmlData .= '{PLAYER1}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="2" class="link2">';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="2" class="topcutCell4">';
							$htmlData .= '{PLAYER14}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="2" class="topcutCell4 winnerCup" style="vertical-align:top">';
							$htmlData .= '{WINNER}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="2" class="topcutCell4">';
							$htmlData .= '{PLAYER23}';
							$htmlData .= '</td>';
							$htmlData .= '<td rowspan="2" class="link2rev">';
							$htmlData .= '</td>';
							$htmlData .= '<td class="topcutCell4">';
							$htmlData .= '{PLAYER2}';
							$htmlData .= '</td>';
							$htmlData .= '</tr>';
							
							$htmlData .= '<tr>';
							$htmlData .= '<td class="topcutCell4">';
							$htmlData .= '{PLAYER4}';
							$htmlData .= '</td>';
							$htmlData .= '<td class="topcutCell4">';
							$htmlData .= '{PLAYER3}';
							$htmlData .= '</td>';
							$htmlData .= '</tr>';
							
							$p1 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[0]->player1['userid']);
							$p2 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[1]->player1['userid']);
							$p3 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[1]->player2['userid']);
							$p4 = strval($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[0]->player2['userid']);
							
							$p23 = '';
							$p14 = '';
							
							$winner = '';
							
							$p1status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[0]['outcome'] == 1)
							{
								$p1status = " topcutWinner";
								$p14 = $p1;
							}
							$p2status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[1]['outcome'] == 1)
							{
								$p2status = " topcutWinner";
								$p23 = $p2;
							}
							$p3status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[1]['outcome'] == 2)
							{
								$p3status = " topcutWinner";
								$p23 = $p3;
							}
							$p4status = "";
							if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound]->matches->match[0]['outcome'] == 2)
							{
								$p4status = " topcutWinner";
								$p14 = $p4;
							}
							if($round == $topCutStartRound+1)
							{
								$p14status = "";
								if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound+1]->matches->match[0]['outcome'] == 1)
								{
									$p14status = " topcutWinner";
									$winner = $p14;
								}
								
								$p23status = "";
								if($xml->pods->pod[$pod]->rounds->round[$topCutStartRound+1]->matches->match[0]['outcome'] == 2)
								{
									$p23status = " topcutWinner";
									$winner = $p23;
								}
								$winnerstatus = " tournamentwinner";
							}
							
							$htmlData = str_replace('{PLAYER1}', '<div class="topcutplayer'.$p1status.'">'.$playersFirstnames[$p1].' '.$playersLastnames[$p1].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER2}', '<div class="topcutplayer'.$p2status.'">'.$playersFirstnames[$p2].' '.$playersLastnames[$p2].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER3}', '<div class="topcutplayer'.$p3status.'">'.$playersFirstnames[$p3].' '.$playersLastnames[$p3].'</div>', $htmlData);
							$htmlData = str_replace('{PLAYER4}', '<div class="topcutplayer'.$p4status.'">'.$playersFirstnames[$p4].' '.$playersLastnames[$p4].'</div>', $htmlData);
							
							if(strlen($p14) > 0)
							{
								$htmlData = str_replace('{PLAYER14}', '<div class="topcutplayer'.$p14status.'">'.$playersFirstnames[$p14].' '.$playersLastnames[$p14].'</div>', $htmlData);
							}
							else
							{
								$htmlData = str_replace('{PLAYER14}', '', $htmlData);
							}
							
							if(strlen($p23) > 0)
							{
								$htmlData = str_replace('{PLAYER23}', '<div class="topcutplayer'.$p23status.'">'.$playersFirstnames[$p23].' '.$playersLastnames[$p23].'</div>', $htmlData);
							}
							else
							{
								$htmlData = str_replace('{PLAYER23}', '', $htmlData);
							}
							
							if(strlen($winner) > 0)
							{
								$htmlData = str_replace('{WINNER}', '<div class="topcutplayer'.$winnerstatus.'">'.$playersFirstnames[$winner].' '.$playersLastnames[$winner].'</div>', $htmlData);
							}
							else
							{
								$htmlData = str_replace('{WINNER}', '', $htmlData);
							}
						}
						
						$htmlData .= '</table></div>';
					}
					$htmlData .= '<table class="pairings" style="width:100%">';
						$htmlData .= '<tr>';
							$htmlData .= '<th style="width:5%;text-align:right">Pts</th>';
							$htmlData .= '<th style="width:5%;text-align:right">Record</th>';
							$htmlData .= '<th style="width:35%;text-align:right">Player 1</th>';
							$htmlData .= '<th style="width:10%;text-align:center">Table</th>';
							$htmlData .= '<th style="width:35%">Player 2</th>';
							$htmlData .= '<th style="width:5%">Record</th>';
							$htmlData .= '<th style="width:5%">Pts</th>';
						$htmlData .= '</tr>';
					
					$nbMatches = sizeof($xml->pods->pod[$pod]->rounds->round[$round]->matches->match);
					$roundType = $xml->pods->pod[$pod]->rounds->round[$round]['type']; //1 : top cut
					if($startTopCut == 999 && $roundType == 1)
						$startTopCut = $r;
					for($m = 0; $m < $nbMatches; $m++)
					{
						$outcome = $xml->pods->pod[$pod]->rounds->round[$round]->matches->match[$m]['outcome'];
						$player = strval($xml->pods->pod[$pod]->rounds->round[$round]->matches->match[$m]->player['userid']);
						$player1 = strval($xml->pods->pod[$pod]->rounds->round[$round]->matches->match[$m]->player1['userid']);
						$player2 = strval($xml->pods->pod[$pod]->rounds->round[$round]->matches->match[$m]->player2['userid']);
						$ts = $xml->pods->pod[$pod]->rounds->round[$round]->matches->match[$m]->timestamp;
						$table = $xml->pods->pod[$pod]->rounds->round[$round]->matches->match[$m]->tablenumber;
						
						$class = "none";
						
						if($outcome == 0)
						{
							
						}
						if($outcome == 5)
						{
							$players[$player]->wins++;
							$players[$player]->AddMatch(3, "BYE");
							$class="winner";
							
							$player1 = "BYE";
							$player2 = "BYE";
						}
						if($outcome == 1)
						{
							$players[$player1]->wins++;
							$players[$player1]->AddMatch(3, $player2);
							$class="winner";
						}
						if($outcome == 2)
						{
							$players[$player1]->losses++;
							$players[$player1]->AddMatch(0, $player2);
							$class="loser";
						}
						if($outcome == 10)
						{
							$players[$player1]->losses++;
							$players[$player1]->AddMatch(0, $player2);
							$class= "dloser";
							
						}
						if($outcome == 8)
						{
							$players[$player]->losses++;
							$players[$player]->AddMatch(0, "LATE");
							$class= "loser";
							
							$player1 = "LATE";
							$player2 = "LATE";
						}
						if($outcome == 3)
						{
							$players[$player1]->ties++;
							$players[$player1]->AddMatch(1, $player2);
							$class= "tie";
							
						}
						$p = $player1;
						if($outcome == 5 || $outcome == 8)
						{
							$p = $player;
						}
						
						$htmlData .= '<tr>';
							$htmlData .= '<td class="'.$class.'" style="text-align:right">';
							$htmlData .= $players[$p]->Points($r);
							$htmlData .= '</td>';
							$htmlData .= '<td class="'.$class.'" style="text-align:right">';
							$htmlData .= $players[$p]->Record($r);
							$htmlData .= '</td>';
							$htmlData .= '<td class="'.$class.'" style="text-align:right">';
							$htmlData .= $playersFirstnames[$p].' '.$playersLastnames[$p];
							$htmlData .= '</td>';
							
							$htmlData .= '<td style="text-align:center">';
							$htmlData .= $table;
							$htmlData .= '</td>';
							
							$class = "none";
							if($outcome == 5)
							{
								$class = "loser";
							}
							if($outcome == 0)
							{
								
							}
							if($outcome == 1)
							{
								$players[$player2]->losses++;
								$players[$player2]->AddMatch(0, $player1);
								$class = "loser";
							}
							if($outcome == 10)
							{
								$players[$player2]->losses++;
								$players[$player2]->AddMatch(0, $player1);
								$class = "dloser";
							}
							if($outcome == 2)
							{
								$players[$player2]->wins++;
								$players[$player2]->AddMatch(3, $player1);
								$class = "winner";
							}
							if($outcome == 3)
							{
								$players[$player2]->ties++;
								$players[$player2]->AddMatch(1, $player1);
								$class = "tie";
							}
							if($outcome == 5)
							{
								$htmlData .= '<td>BYE</td><td></td><td></td>';
							}
							else
							{
								if($outcome == 8)
								{
									$htmlData .= '<td>LATE</td><td></td><td></td>';
								}
								else
								{
									$p = $player2;
									$htmlData .= '<td class="'.$class.'">';
									$htmlData .= $playersFirstnames[$p].' '.$playersLastnames[$p];
									$htmlData .= '</td><td class="'.$class.'">';
									$htmlData .= $players[$p]->Record($r);
									$htmlData .= '</td><td class="'.$class.'">';
									$htmlData .= $players[$p]->Points($r);
									$htmlData .= '</td>';
								}
							}
						$htmlData .= '</tr>';
					}
					$htmlData .= '</table>';
					
					if($roundType != 1)
					{
						for($k = 0; $k < $nbPodPlayers; $k++)
						{
							$index = $podPlayersID[$k];
							$players[$index]->ComputeOppResistance($players, $round);
						}
						for($k = 0; $k < $nbPodPlayers; $k++)
						{
							$index = $podPlayersID[$k];
							$players[$index]->ComputeOppOppResistance($players, $round);
						}
					
						for($k = 0; $k < $nbPodPlayers-1; $k++)
						{
							if($players[$podPlayersID[$k]]->points < $players[$podPlayersID[$k+1]]->points)
							{
								$temp = $podPlayersID[$k];
								$podPlayersID[$k] = $podPlayersID[$k+1];
								$podPlayersID[$k+1] = $temp;
								$k = -1;
							}
							else
							{
								if($players[$podPlayersID[$k]]->points == $players[$podPlayersID[$k+1]]->points)
								{
									if($players[$podPlayersID[$k]]->oppresistance[$r-1] < $players[$podPlayersID[$k+1]]->oppresistance[$r-1])
									{
										$temp = $podPlayersID[$k];
										$podPlayersID[$k] = $podPlayersID[$k+1];
										$podPlayersID[$k+1] = $temp;
										$k = -1;
									}
									else
									{
										if($players[$podPlayersID[$k]]->oppresistance[$r-1] == $players[$podPlayersID[$k+1]]->oppresistance[$r-1])
										{
											if($players[$podPlayersID[$k]]->oppoppresistance[$r-1] < $players[$podPlayersID[$k+1]]->oppoppresistance[$r-1])
											{
												$temp = $podPlayersID[$k];
												$podPlayersID[$k] = $podPlayersID[$k+1];
												$podPlayersID[$k+1] = $temp;
												$k = -1;
											}
										}
									}
								}
							}
						}
					}
					else
					{
						for($k = 0; $k < $nbPodPlayers-1; $k++)
						{
							if(sizeof($players[$podPlayersID[$k]]->games) == $r && sizeof($players[$podPlayersID[$k]]->games) == sizeof($players[$podPlayersID[$k+1]]->games) && $players[$podPlayersID[$k]]->games[sizeof($players[$podPlayersID[$k]]->games)-1] == 0 && $players[$podPlayersID[$k+1]]->games[sizeof($players[$podPlayersID[$k+1]]->games)-1] == 3)
							{
								$temp = $podPlayersID[$k];
								$podPlayersID[$k] = $podPlayersID[$k+1];
								$podPlayersID[$k+1] = $temp;
								$k = -1;
							}
						}
					}
				//$roundstanding = True;
					if($roundstanding == True)
					{
						$htmlData .= '<br><p1>Round standings</p1><br><table><tr><th>#</th><th>Player</th><th>Record</th><th>Points</th>';
						if($resistances == True)
						{
							$htmlData .= '<th>%Opp</th><th>%OppOpp</th>';
						}
						$htmlData .= '</tr>';
						for($k = 0; $k < $nbPodPlayers; $k++)
						{
							$htmlData .= '<tr>';
							$index = $podPlayersID[$k];
							$htmlData .= '<td>'.strval($k+1).'</td>';
							$htmlData .= '<td>'.$playersFirstnames[$index].' '.$playersLastnames[$index].'</td>';
							$htmlData .= '<td>'.$players[$index]->wins.'/'.$players[$index]->losses.'/'.$players[$index]->ties.'</td>';
							if($roundType != 1 || sizeof($players[$index]->opponents) < $startTopCut)
							{
								$htmlData .= '<td>'.$players[$index]->points.'</td>';
								if($resistances == True)
								{
									$htmlData .= '<td>'.number_format($players[$index]->GetORes($round)*100.0, 2).'</td>';
									$htmlData .= '<td>'.number_format($players[$index]->GetOORes($round)*100.0, 2).'</td>';
								}
							}
							else
							{
								$htmlData .= '<td></td>';
								if($resistances == True)
								{
									$htmlData .= '<td></td><td></td>';
								}
							}
							$htmlData .= '</tr>';
						}
						$htmlData .= '</table>';
					}
					$htmlData .= '</div>';
				}
				//Standings
				$htmlData .= '<div id="S'.$pod.'" class="tabcontent subcontent">';
					$podCat = $definedPods[$pod];
					if(isset($xml->standings) && isset($xml->standings->pod))
					{
						for($i = 0; $i < sizeof($xml->standings->pod); $i++)
						{
							if(strcmp($xml->standings->pod[$i]['type'], 'finished') == 0)
							{
								if(		($podCat == 0 && $xml->standings->pod[$i]['category'] == 0) ||
										($podCat == 1 && $xml->standings->pod[$i]['category'] == 1) ||
										($podCat == 2 && $xml->standings->pod[$i]['category'] == 2) ||
										($podCat == 9 && $xml->standings->pod[$i]['category'] == 1) ||
										($podCat == 9 && $xml->standings->pod[$i]['category'] == 2) ||
										($podCat == 8 && $xml->standings->pod[$i]['category'] == 0) ||
										($podCat == 8 && $xml->standings->pod[$i]['category'] == 1) ||
										($podCat == 10 && $xml->standings->pod[$i]['category'] == 0) ||
										($podCat == 10 && $xml->standings->pod[$i]['category'] == 1) ||
										($podCat == 10 && $xml->standings->pod[$i]['category'] == 2)
								)
								{
									switch ($xml->standings->pod[$i]['category']) 
									{
										case 0:$htmlData .= '<b>Juniors</b><br>';break;
										case 1:$htmlData .= '<b>Seniors</b><br>';break;
										case 2:$htmlData .= '<b>Masters</b><br>';break;
									}
									$htmlData .= '<table>';
									$htmlData .= '<tr>';
										$htmlData .= '<th style="width:10%">Placement</th>';
										//$htmlData .= '<th>Id</th>';
										$htmlData .= '<th style="width:10%">Record</th>';
										$htmlData .= '<th style="width:45%">Name</th>';
										$htmlData .= '<th style="width:45%">Surname</th>';
									$htmlData .= '</tr>';
									$nbPlaces = sizeof($xml->standings->pod[$i]->player);
									for($p = 0; $p < $nbPlaces; $p++)
									{
										$htmlData .= '<tr>';
										$htmlData .= '<td>';
										$htmlData .= $xml->standings->pod[$i]->player[$p]['place'];
										$htmlData .= '</td>';
										/*$htmlData .= '<td>';
										$htmlData .= $xml->standings->pod[0]->player[$p]['id'];
										$htmlData .= '</td>';*/
										$htmlData .= '<td>';
										$htmlData .= $players[strval($xml->standings->pod[$i]->player[$p]['id'])]->Record(0);
										$htmlData .= '</td>';
										$nbPlayers = sizeof($xml->players->player);
										for($k = 0; $k < $nbPlayers; $k++)
										{
											if(strcmp($xml->players->player[$k]['userid'], $xml->standings->pod[$i]->player[$p]['id']) == 0)
											{
												//$lastName = $xml->players->player[$k]->lastname[0];
												//if($xml->standings->pod[$i]['category'] == 2)
												//{
													$lastName = $xml->players->player[$k]->lastname;	
												//}
												$firstName = $xml->players->player[$k]->firstname;
											}
										}
										$htmlData .= '<td>';
										$htmlData .= $lastName;
										$htmlData .= '</td>';
										$htmlData .= '<td>';
										$htmlData .= $firstName;
										$htmlData .= '</td>';
										$htmlData .= '</tr>';
									}
									$htmlData .= '</table>';
								}
							}
						}
					}
				$htmlData .= '</div>';
				$htmlData .= '</div>';
			}
			$htmlData .= '</div>';
		}
		else
		{
			$url = htmlspecialchars($_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/?folder='.str_replace(' ', '_space_', $dir));
			$url = 'https://'.$url;
			$shopName = '';
			if(file_exists($user_data))
			{
				$jsonData = json_decode(file_get_contents($user_data), true);
				if (array_key_exists("leaguename",$jsonData))
				{
					$shopName = $jsonData['leaguename'];
				}
			}
			$pageTitle = $unprotectedName;
			$tournamentTitle = $unprotectedName;
			
			$scriptData .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>';
			$htmlData .= '<div id="main">';
			
			$htmlData .= '<div class="header">';
			$htmlData .= '<div class="column tournament" id="pdfToHide"><button class="pushable trmnt" onclick="showImage()"><span class="front">'.$unprotectedName.'</span></button><img id="image" src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl='.$url.'&choe=UTF-8" title="Link to this tournament" style="display: none;margin-left: auto;margin-right: auto;width: 50%;"/>';
			$htmlData .= '<div id="tohide" hidden>';
			$htmlData .= '<a href="'.$url.'">Lien</a><br>';
			$htmlData .= '<button id="pdfbutton" onclick="toPDF()"></button>';
			$htmlData .= $hiddenStream;
			$htmlData .= '</div>';
			$htmlData .= '</div>';
			$htmlData .= '<div class="column league"><div class="tTitle">'.$tournamentTitle.'</div><div>----</div><div class="sName">'.$shopName.'</div></div>';
			$htmlData .= '<div class="column logo"><img src="logo.png" alt="Logo" width="250px"></div>';
			$htmlData .= '</div>';
			if((isset($_SESSION["password"]) && isset($_SESSION["league"]) && $_SESSION["league"] == explode('/', getcwd())[sizeof(explode('/', getcwd()))-1]))
			{
				$htmlData .= '<form action="" method="post" id="deleteTournament">';
				$htmlData .= '<input id="delete" name="delete" value="'.$dir.'" hidden>';
				$htmlData .= '<p><button class="pushable" type="submit" onclick="return confirm(\'Are you sure?\')"><span class="front">Delete Tournament</span></button></p>';
				$htmlData .= '</form>';
			}
		}
	}
}
?>
<html>
<head>
<title>TDF Viewer</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php echo $scriptData;?>
<style>
body, html
{
	height: 100%;
	overflow-y:scroll;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}

.tTitle
{
	font-family: Arial, Helvetica, sans-serif;
	font-size: 30px;
}

.sName
{
	font-family: Arial, Helvetica, sans-serif;
	font-size: 20px;
}

.header:after {
  content: "";
  display: table;
  clear: both;
  height:100px;
}

.column {
  float: left;
  width: 33%;
}

.league
{
	text-align: center;
}

.logo img
{
	position: relative;
	top: 0px;
	right: 0px;
}

.tournament
{
  
}

.topcutWinner
{
	background-color: #def0de;
}

.tournamentwinner
{
	background-color: #FFD700;
}


body::-webkit-scrollbar
{
    display: none;
}
body
{
    -ms-overflow-style: none;
}
body
{
    scrollbar-width: none;
}

.row::-webkit-scrollbar
{
    display: none;
}
.row
{
    -ms-overflow-style: none;
}
.row
{
    scrollbar-width: none;
}

.column2
{
    -ms-overflow-style: none;
}
.column2
{
    scrollbar-width: none;
}
.column2::-webkit-scrollbar
{
    display: none;
}

.topcutplayer
{
	height:30px;
	width:100%;
	text-align: center;
	line-height: 30px;
	overflow: hidden;
	border:1px solid black;
}
  
.row
{
	height: 100%;
	overflow:auto;
	max-height: 100vh;
}
.column1, .column2
{
	height: 90%;
}

.row { width: 100%; }

.topcutCell
{
	width:14%;
	text-align: center;
	height:60px;
	border:none;
}

.topcutCell4
{
	width:14%;
	text-align: center;
	height:120px;
	border:none;
}

/* Create two equal columns that floats next to each other */
.column1 {
  width: 25%;
  padding: 10px;
  background-color:#D3D3D3;
}

.column2 {
 width: 70%;
  padding: 10px;
  background-color:#D3D3D3;
  overflow-y:scroll;
}

.pushable
{
    background: <?php echo $color1;?>;
    border-radius: 12px;
    border: none;
    padding: 0;
    cursor: pointer;
    outline-offset: 4px;
	margin: 10px;
	width:250px;
}

.front:hover
{
    background: white;
	color: black;
}

.trmnt
{
	width:400px;
}

.front
{
    display: block;
    padding: 12px 42px;
    border-radius: 12px;
    font-size: 1.25rem;
    background: <?php echo $color2;?>;
    color: white;
    transform: translateY(-6px);
 }

.pushable:active .front
{
    transform: translateY(-2px);
}

.pushed
{
	background: black;
}

.mobilemenu
{
	display:none;
}

.column1, .column2 { display:inline-block; vertical-align: top;}

	  
body, html
{
	width: 100%; 
	margin: 0; 
	padding: 0;
	background-color:#D3D3D3;
}

.button {
  background-color: #555555; /* Green */
  border: none;
  color: white;
  padding: 15px 32px;
  text-align: center;
  text-decoration: none;
  display: inline-block;
  font-size: 16px;
  width:200px;
  border-radius: 12px;
  margin:10px;
}

@media only screen and (max-width: 800px)
{
	.button 
	{
		background-color: #555555;
		border: none;
		color: white;
		padding: 5px 15px;
		text-align: center;
		text-decoration: none;
		display: inline-block;
		font-size: 14px;
		width:100%;
		border-radius: 12px;
		margin:10px;
	}
}

.frame
{ 
	border: none; 
	margin: 0; 
	padding: 0; 
	width:100%;
	height:100%;
}

.button
{  
	background-color: #555555;
	border: none;  
	color: white;  
	padding: 15px 32px;  
	text-align: center;  
	text-decoration: none;  
	display: inline-block;  
	font-size: 16px;  
	width:200px;  
	border-radius: 12px;  
	margin:10px;
}

.custom-file-upload
{
	border: 1px solid #ccc;
	display: inline-block;
	padding: 6px 12px;   
	cursor: pointer;
}

#password_form
{
	border: 2px solid #73AD21;
	text-align:center;
	width: 400px;
	margin:10px;
	border-radius: 25px;
}

.adminforms
{
	border: 2px solid #73AD21;
	margin:10px;
	padding: 10px;
	border-radius: 25px;
}

body {font-family: Arial;}

.fcc-btn {
  background-color: #555555; /* Green */
  border: none;
  color: white;
  padding: 15px 32px;
  text-align: center;
  text-decoration: none;
  display: inline-block;
  font-size: 16px;
  width:200px;
  border-radius: 12px;
  margin:10px;
}

.link4
{
	border:0px;
	background-image: url("../master/link4.png");
	background-position: center center;
}

.link2
{
	border:0px;
	background-image: url("../master/link2.png");
	background-position: center center;
}

.link4rev
{
	border:0px;
	background-image: url("../master/link4rev.png");
	background-position: center center;
}

.link2rev
{
	border:0px;
	background-image: url("../master/link2rev.png");
	background-position: center center;
}

.winnerCup
{
	background-image: url("../master/link1.png");
	background-position: center center;
}

/* Style the tab */
.tab {
  overflow: hidden;
  border: 1px solid #ccc;
  background-color: #f1f1f1;
}

.topcut
{
	color:blue;
}

/* Style the buttons inside the tab */
.tab button {
  background-color: inherit;
  float: left;
  border: none;
  outline: none;
  cursor: pointer;
  padding: 14px 16px;
  transition: 0.3s;
  font-size: 17px;
}

/* Change background color of buttons on hover */
.tab button:hover {
  background-color: #ddd;
}

/* Create an active/current tablink class */
.tab button.active {
  background-color: #ccc;
}


/* Style the tab content */
.tabcontent {
  display: none;
  padding: 6px 12px;
  border: 1px solid #ccc;
  border-top: none;
	/*animation: fadeEffect 1s;*/ /* Fading effect takes 1 second */
}

#pdfbutton
{
	background: url(../ressources/pdf.png);
	background-size: 50px;
	width:50px;
	height:50px;
}
.winner
{
	background-color: #def0de;
}

.tie
{
	background-color: #fdf0d3;
}

.dloser
{
	background-color: #BC8F8F;
}

/* Go from zero to full opacity */
@keyframes fadeEffect {
  from {opacity: 0;}
  to {opacity: 1;}
}

table {
  font-family: arial, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

td, th {
  border: 1px solid #dddddd;
  text-align: left;
  padding: 3px;
  color:black;
}
/*
tr:nth-child(even) {
  background-color: #dddddd;
}*/


@media (max-width: 1000px) 
{
	.mobilemenu
	{
		display:block;
	}
	.column1, .column2
	{ 
		display:block;
		vertical-align: top;
	}
	.column1
	{ 
		height:450px;
		display:none;
		text-align: center;
		width:100%;
		background-color:#778899;
	}
	.column2
	{
		width: 100%;
		padding: 0px;
	}
	body, html, .row
	{
		height: 100%;
		overflow: auto;
	}
	.trmnt
	{
		width:80%;
	}
	
	
	.header:after 
	{
		content: "";
		display: block;
		clear: both;
		height:auto;
	}

	.column
	{
		float: none;
		width: 100%;
		text-align: center;
	}

	.league
	{
		
	}

	.logo img
	{
		position: relative;
		top: 0px;
		right: 0px;
		margin-left: auto;
		margin-right: auto;
		width: 50%;
	}

	.tournament
	{
	  
	}
	
	.tabcontent
	{
		padding: 0px 0px;
	}
}

</style>
</head>
<body>
<div class="mobilemenu">
<a href="javascript:void(0);" class="icon" onclick="showMenu()">
    <img src="../ressources/menu.png">
</a>
</div>
<div class="row">
<div class="column1" id="column1">
	<form action="." method="post">
		<input id="current" name="current" hidden>
		<p><button class="pushable<?php if(isset($_POST['current']))echo " pushed";?>" type="submit"><span class="front">Current Tournament(s)</span></button></p>
	</form>
	<form action="." method="post">
		<input id="archives" name="archives" hidden>
		<p><button class="pushable<?php if(isset($_POST['archives']))echo " pushed";?>" type="submit"><span class="front">Archives</span></button></p>
	</form>
	<form action="." method="post">
		<input id="upload" name="upload" hidden>
		<p><button class="pushable<?php if(isset($_POST['upload']))echo " pushed";?>" type="submit"><span class="front">Upload TDF</span></button></p>
	</form>
	<form action="." method="post">
		<input id="add" name="add" hidden>
		<p><button class="pushable<?php if(isset($_POST['add']))echo " pushed";?>" type="submit"><span class="front">Add Tournament</span></button></p>
	</form>
	<form action="." method="post">
		<input id="admin" name="admin" hidden>
		<p><button class="pushable<?php if(isset($_POST['admin']))echo " pushed";?>" type="submit"><span class="front">Administration</span></button></p>
	</form>
</div>
	<div class="column2" id="column2">
	
	<?php
		echo $htmlData;
	?>
	</div>
</div>

</body>

<script>
function showMenu()
{
	var col1 = document.getElementById("column1");
	var col2 = document.getElementById("column2");
	if (col1.style.display === "block")
	{
		col1.style.display = "none";
		col2.style.display = "block";
	}
	else
	{
		col1.style.display = "block";
		col2.style.display = "none";
	}
}
function toggleHidden()
{	
	let button = document.getElementById("toggle");
	let sel = document.getElementById("tournament");
	sel.setAttribute("value", "");
	let elements = document.getElementsByClassName("hidden");
	for(var i = 0; i < elements.length; i++)
	{
		let hidden = elements[i].getAttribute("hidden");
		
		if (hidden) 
		{
		   elements[i].removeAttribute("hidden");
		   button.textContent = "Hide finished";
		} 
		else
		{
		   elements[i].setAttribute("hidden", "hidden");
		   button.textContent = "Show finished";
		}
	}
	let elements2 = document.getElementsByClassName("first");
	if(elements2.length > 0)
	{
		elements2[0].setAttribute('selected', 'selected')
		sel.selectedIndex = 0;
	}
}
if(document.getElementById("defaultOpen"))
	document.getElementById("defaultOpen").click();


function openTab(evt, tabName)
{
	var i, tabcontent, tablinks;
	tabcontent = document.getElementsByClassName("tabcontent");
	//Hide all tabs
	for (i = 0; i < tabcontent.length; i++)
	{
		tabcontent[i].style.display = "none";
	}
	tablinks = document.getElementsByClassName("tablinks");
	for (i = 0; i < tablinks.length; i++)
	{
		tablinks[i].className = tablinks[i].className.replace(" active", "");
	}
	//show selected tab
	document.getElementById(tabName).style.display = "block";
	evt.currentTarget.className += " active";
	
	if(tabName[0] == 'P')
	{
		if(document.getElementById("defaultOpen"+tabName))
		{
			document.getElementById("defaultOpen"+tabName).click();
		}
	}
}

function subopenTab(evt, tabName)
{
	var i, tabcontent, tablinks;
	tabcontent = document.getElementsByClassName("subcontent");
	//Hide all tabs
	for (i = 0; i < tabcontent.length; i++)
	{
		tabcontent[i].style.display = "none";
	}
	tablinks = document.getElementsByClassName("subtablinks");
	for (i = 0; i < tablinks.length; i++)
	{
		tablinks[i].className = tablinks[i].className.replace(" active", "");
	}
	//show selected tab
	document.getElementById(tabName).style.display = "block";
	evt.currentTarget.className += " active";
}
function showImage()
{
	if(document.getElementById('image').style.display == "none")
	{
		document.getElementById('image').style.display = "block";
		document.getElementById('tohide').style.display = "block";
	}
	else
	{
		document.getElementById('image').style.display = "none";
		document.getElementById('tohide').style.display = "none";
	}
}
window.parent.document.title = "<?php echo $pageTitle;?>";

function toPDF()
{
	color = document.body.style.backgroundColor;
	document.body.style.backgroundColor = "white";
	var toHide = document.getElementById('pdfToHide');
	toHide.style.display = "none";
	var tabs = document.getElementsByClassName('tab');
	for(i = 0; i < tabs.length; i++)
	{
		tabs[i].style.display = "none";
	}
	
	var ths = document.getElementsByClassName('th');
	for(i = 0; i < ths.length; i++)
	{
		ths[i].style.color = "black";
		ths[i].style.bgcolor = "white";
	}
	var tds = document.getElementsByClassName('td');
	for(i = 0; i < tds.length; i++)
	{
		tds[i].style.color = "black";
		tds[i].style.bgcolor = "white";
	}
	
	var element = document.getElementById('main');
	var opt = {
			  margin:       0,
			  filename:     'standings.pdf',
			  image:        { type: 'jpeg', quality: 1.00 },
			  html2canvas:  { scale: 4 },
			  jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
			};
	
	html2pdf().set(opt).from(element).save().then(() => {
		toHide.style.display = "block";
		for(i = 0; i < tabs.length; i++)
		{
			tabs[i].style.display = "block";
		}
		document.body.style.backgroundColor = color;
	});
}
</script>
</html>
