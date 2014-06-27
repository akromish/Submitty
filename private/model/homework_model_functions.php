<?php

// This file is relative to the public directory of the website.  (It
// is run from the location of index.php). 
// static $path_to_path_file = "../../site_path.txt"; 
static $path_to_path_file = "site_path.txt";


//This will be changed to whatever exists in the above file
static $path_front = "";
function get_path_front() {
    global $path_front;
    global $path_to_path_file;
    if ($path_front == "") {
        if (!file_exists($path_to_path_file)) {
            ?><script>alert("<?php echo $path_to_path_file?> does not exist.  Please make this file or edit the path in private/model/homework_model_functions.  The file should contain a single line of the path to the directory folder (ex: csci1200).  No whitespaces or return characters.");</script>
            <?php exit();
        }

        $file = fopen($path_to_path_file, 'r');
        $path_front = trim(fgets($file));
        fclose($file);
    }
    return $path_front;
}



function display_file_permissions($perms) {
  if (($perms & 0xC000) == 0xC000) {
    // Socket
    $info = 's';
  } elseif (($perms & 0xA000) == 0xA000) {
    // Symbolic Link
    $info = 'l';
  } elseif (($perms & 0x8000) == 0x8000) {
    // Regular
    $info = '-';
  } elseif (($perms & 0x6000) == 0x6000) {
    // Block special
    $info = 'b';
  } elseif (($perms & 0x4000) == 0x4000) {
    // Directory
    $info = 'd';
  } elseif (($perms & 0x2000) == 0x2000) {
    // Character special
    $info = 'c';
  } elseif (($perms & 0x1000) == 0x1000) {
    // FIFO pipe
    $info = 'p';
  } else {
    // Unknown
    $info = 'u';
  }
  
  // Owner
  $info .= (($perms & 0x0100) ? 'r' : '-');
  $info .= (($perms & 0x0080) ? 'w' : '-');
  $info .= (($perms & 0x0040) ?
            (($perms & 0x0800) ? 's' : 'x' ) :
            (($perms & 0x0800) ? 'S' : '-'));
  
  // Group
  $info .= (($perms & 0x0020) ? 'r' : '-');
  $info .= (($perms & 0x0010) ? 'w' : '-');
  $info .= (($perms & 0x0008) ?
            (($perms & 0x0400) ? 's' : 'x' ) :
            (($perms & 0x0400) ? 'S' : '-'));
  
  // World
  $info .= (($perms & 0x0004) ? 'r' : '-');
  $info .= (($perms & 0x0002) ? 'w' : '-');
  $info .= (($perms & 0x0001) ?
            (($perms & 0x0200) ? 't' : 'x' ) :
            (($perms & 0x0200) ? 'T' : '-'));
  
  echo $info;
}


// Upload HW Assignment to server and unzip
function upload_homework($username, $assignment_id, $homework_file) {
    $path_front = get_path_front();

    // Check user and assignment authenticity
    $class_config = get_class_config($username);
    if ($username !== $_SESSION["id"]) {//Validate the id
        display_error("User Id invalid.  ".$username." != ".$_SESSION["id"]);
        return;
    }
    if (!is_valid_assignment($class_config, $assignment_id)) {
        display_error($assignment_id." is not a valid assignment");
        return;
    }
    $assignment_config = get_assignment_config($username, $assignment_id);
    if (!can_edit_assignment($username, $assignment_id, $assignment_config)) {//Made sure the user can upload to this homework
        display_error($assignment_id." is closed.  Unable to change");
        return;
    }
    //VALIDATE HOMEWORK CAN BE UPLOADED HERE
    //ex: homework number, due date, late days

    $max_size = 50000;//CHANGE THIS TO GET VALUE FROM APPROPRIATE FILE
    $allowed = array("zip");
    $filename = explode(".", $homework_file["name"]);
    $extension = end($filename);


    // TODO should support more than zip (.tar.gz etc.)
    if (!($homework_file["type"] === "application/zip")) {//Make sure the file is a zip file
        display_error("Incorrect file upload type.  Not a zip, got ".htmlspecialchars($homework_file["type"]));
        return;
    }

    // make folder for this homework (if it doesn't exist)
    $assignment_path = $path_front."/submissions/".$assignment_id;
    if (!file_exists($assignment_path)) {
        if (!mkdir($assignment_path))
        {
            display_error("Failed to make folder ".$assignment_path);
            return;
        }
    }
    // which group is sticky, but need to set group read access	  
		  //chmod($assignment_path,"0750");

    // make folder for this user (if it doesn't exist)
    $user_path = $assignment_path."/".$username;
    // If user path doesn't exist, create new one
    if (!file_exists($user_path)) {
        if (!mkdir($user_path))
        {
            display_error("Failed to make folder ".$user_path);
            return;
        }
    }
   // which group is sticky, but need to set group read access	  
//   chmod($user_path,"0750");

    //Find the next homework version number

    $i = 1;
    while (file_exists($user_path."/".$i)) {
		  // FIXME: should not exist?
        //Replace with symlink?
        $i++;
    }

    // Attempt to create folder
    $version_path = $user_path."/".$i;
    if (!mkdir($version_path)) {//Create a new directory corresponding to a new version number
        display_error("Failed to make folder ".$version_path);
        return;
    }

    $perms = fileperms($version_path);
    display_file_permissions($perms);

    // which group is sticky, but need to set group read access	  
		  //chmod($version_path,"0750");

    $perms = fileperms($version_path);
    display_file_permissions($perms);

    // which group is sticky, but need to set group read access	  
		  //chmod($version_path,"0544");

    $perms = fileperms($version_path);
    display_file_permissions($perms);

    // Unzip files in folder
    $zip = new ZipArchive;
    $res = $zip->open($homework_file["tmp_name"]);
    if ($res === TRUE) {
      $zip->extractTo($version_path."/");
      $zip->close();
    } else {
        display_error("failed to move uploaded file from ".$homework_file["tmp_name"]." to ". $version_path."/".$homework_file["name"]);
        return;
    }
    $settings_file = $user_path."/user_assignment_settings.json";
    if (!file_exists($settings_file)) {
        $json = array("selected_assignment"=>1);
        file_put_contents($settings_file, json_encode($json));
    }
    $to_be_compiled = $path_front."/submissions/to_be_compiled.txt";
    if (!file_exists($to_be_compiled)) {
        file_put_contents($to_be_compiled, $assignment_id."/".$username."/".$i."\n");
    } else {
        $text = file_get_contents($to_be_compiled, false);
        $text = $text.$assignment_id."/".$username."/".$i."\n";
        file_put_contents($to_be_compiled, $text);
    }

    // which group is sticky, but need to set group read access	  
	    //chmod($version_path."/*".$i,"g+r");

    return array("success"=>"File uploaded successfully");
}

// Check if user has permission to edit homework
function can_edit_assignment($username, $assignment_id, $assignment_config) {

	    // HACK!  To not check due date
	    return true;

    $path_front = get_path_front();
    date_default_timezone_set('America/New_York');
    $file = $path_front."/results/".$assignment_id."/".$username."/user_assignment_config.json";
    if (file_exists($file)) {
        $json = json_decode(file_get_contents($file), true);
        if (isset($json["due_date"]) && $json["due_date"] != "default") {
            $date = new DateTime($json["due_date"]);
            $now = new DateTime("NOW");
            return $now <= $date;
        }
        return; //TODO
    }
    $date = new DateTime($assignment_config["due_date"]);
    $now = new DateTime("NOW");
    return $now <= $date;
}


//Gets the class information for assignments

function get_class_config($username) {
    $path_front = get_path_front();
    $file = $path_front."/config/class.json";
//    $file = $path_front."/results/class.json";
    if (!file_exists($file)) {
        ?><script>alert("Configuration for this class (<?php echo $file ?>) does not exist. Quitting.");</script>
        <?php exit();
    }
    return json_decode(file_get_contents($file), true);
}


// Find most recent submission from user
function most_recent_assignment_version($username, $assignment_id) {
    $path_front = get_path_front();
    $path = $path_front."/submissions/".$assignment_id."/".$username;
    $i = 1;
    while (file_exists($path."/".$i)) {
        $i++;
    }
    return $i - 1;

}

// Get name for assignment
function name_for_assignment_id($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if ($one["assignment_id"] == $assignment_id) {
            return $one["assignment_name"];
        }
    }
    return "";//TODO Error handling
}

// Check to make sure instructor has added this assignment
function is_valid_assignment($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if ($one["assignment_id"] == $assignment_id) {
            return true;
        }
    }
    return false;
}

// Make sure student has actually submitted this version of an assignment
function is_valid_assignment_version($username, $assignment_id, $assignment_version) {
    $path_front = get_path_front();
    $path = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version;
    return file_exists($path);
}


// Get TA grade for assignment
function TA_grade($username, $assignment_id) {
    //TODO
    return false;
}

function version_in_grading_queue($username, $assignment_id, $assignment_version) {
    $path_front = get_path_front();
    if (!is_valid_assignment_version($username, $assignment_id, $assignment_version)) {//If its not in the submissions folder
        return false;
    }
    $file = $path_front."/results/".$assignment_id."/".$username."/".$assignment_version;
    if (file_exists($file)) {//If the version has already been graded
        return false;
    }
    return true;
}


//RESULTS DATA

// Get the test cases from the instructor configuration file
function get_assignment_config($username, $assignment_id) {
    $path_front = get_path_front();
    $file = $path_front."/results/".$assignment_id."/assignment_config.json";
    if (!file_exists($file)) {
        return false;//TODO Handle this case
    }
    return json_decode(file_get_contents($file), true);
}

// Get results from test cases for a student submission
function get_assignment_results($username, $assignment_id, $assignment_version) {
    $path_front = get_path_front();
    $file = $path_front."/results/".$assignment_id."/".$username."/".$assignment_version."/submission.json";
    if (!file_exists($file)) {
        return false;
    }
    return json_decode(file_get_contents($file), true);
}



//SUBMITTING VERSION

function get_user_submitting_version($username, $assignment_id) {
    $path_front = get_path_front();
    $file = $path_front."/submissions/".$assignment_id."/".$username."/user_assignment_settings.json";
    if (!file_exists($file)) {
        return 0;
    }
    $json = json_decode(file_get_contents($file), true);
    return $json["selected_assignment"];
}

function change_assignment_version($username, $assignment_id, $assignment_version, $assignment_config) {
    if (!can_edit_assignment($username, $assignment_id, $assignment_config)) {
        display_error("Error: This assignment ".$assignment_id." is not open.  You may not edit this assignment.");
        return;
    }
    if (!is_valid_assignment_version($username, $assignment_id, $assignment_version)) {
        display_error("This assignment version ".$assignment_version." does not exist");
        return;
    }
    $path_front = get_path_front();
    $file = $path_front."/submissions/".$assignment_id."/".$username."/user_assignment_settings.json";
    if (!file_exists($file)) {
        display_error("Unable to find user settings.  Looking for ".$file);
        return;
    }
    $json = json_decode(file_get_contents($file), true);
    $json["selected_assignment"] = $assignment_version;
    file_put_contents($file, json_encode($json));
    return array("success"=>"Success");
}

//DIFF FUNCTIONS

// Converts the JSON "diff" field from submission.json to an array containing
// file contents
function get_testcase_diff($username, $assignment_id, $assignment_version, $diff){
    $path_front = get_path_front();

    if (!isset($diff["instructor_file"]) ||
        !isset($diff["student_file"]) ||
        !isset($diff["difference"])) {
        return "";
    }

    $instructor_file_path = "$path_front/".$diff["instructor_file"];
    $student_path = "$path_front/results/$assignment_id/$username/$assignment_version/";

    if (!file_exists($instructor_file_path) ||
        !file_exists($student_path . $diff["student_file"]) ||
        !file_exists($student_path . $diff["difference"])){
        return "";
    }

    $student_content = file_get_contents($student_path.$diff["student_file"]);
    $difference = file_get_contents($student_path.$diff["difference"]);
    $instructor_content = file_get_contents($instructor_file_path);

    return array(
            "student" => $student_content,
            "instructor"=> $instructor_content,
            "difference" => $difference
        );
}

//ERRORS

function display_error($error) {
    ?>
    <script>alert("Error: <?php echo $error;?>");</script>
    <?php
//       echo get_current_user();
    exit();
}
