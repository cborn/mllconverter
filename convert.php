<?php
/*
* Converts backup language lesson files to json format for use
* with Language Lesson (Ruby) app.
* Saves converted lessons to relevant course folder in converted_lessons directory.
* Naming convention for lessons:
*   if no table of contents: lessontitle
*   if one table of contents: lessontitle_ordernumber_nameofsection or same as below
*   if multiple table of contents: lessontitle_ordernumber_nameoftable_nameofsection
*/
header('Content-Type: text/html; charset=UTF-8');

ini_set('max_execution_time', 120);

//include('functions.php');
// upload XML functions
$allowedExts = array('xml');
$temp = explode(".", $_FILES['file']['name']);
$extension = end($temp);

if (($_FILES['file']['type'] == 'text/xml') && in_array($extension, $allowedExts)) {
    if ($_FILES["file"]["error"] > 0) {
        echo 'Error: ' . $_FILES['file']['error'] . '<br>';
    }
} else {
    echo "Invalid file type for languagelesson.xml";
}

$domain = $_POST['domain'];
$path = $_POST['path'];
$file = $_FILES['file']['tmp_name'];
$filelist = $path . '/files.xml';
echo 'Working...<br>';

///////////////////////////
// start of actual script
///////////////////////////

// create dir to separate converted lessons from code, audio, and backup files
$main_dir = 'converted_lessons';
if (!file_exists($main_dir)) {
    mkdir($main_dir);
}
$course_name = ltrim(strrchr($path,'/'), '/');
$course_dir = 'converted_lessons/'.$course_name;
if (!file_exists($course_dir)) {
    mkdir($course_dir);
}

$dom = new DOMDocument();

$dom->load($file);
$llname = $dom->getElementsByTagName('name')->item(0)->textContent;
$autograde = $dom->getElementsByTagName('autograde')->item(0)->textContent;

if (!is_file($path.'/filescondensed.xml')) {
    $dom2 = new DOMDocument();

    $dom2->load($filelist);
    $files = $dom2->getElementsByTagName('file');

    // create file of necessary file hashes
    $file_condensed = fopen($path.'/filescondensed.xml','w');
    foreach ($files as $file) {
        $filename = $file->getElementsByTagName('filename')->item(0)->textContent;
        $filehash = $file->getElementsByTagName('contenthash')->item(0)->textContent;
        if ($filename != '.') {
            $fileline = $filename.','.$filehash."\n";
            fwrite($file_condensed,$fileline);
        }
    }
    fclose($file_condensed);
}

//speed up file search process- only have to parse files.xml once per course
$file_condensed = file($path.'/filescondensed.xml',FILE_IGNORE_NEW_LINES);
$filehashes = array();
foreach ($file_condensed as $file){
    $filearray = explode(',',$file);
    $filehashes[$filearray[0]] = $filearray[1];
}

if ($autograde = false) {
	$graded = true;
} else {
	$graded = false;
}

//change spaces and / to _ in name
$llname = preg_replace('/\s|\//', '_', $llname);

// create empty json text file
$json_file = $llname . '.json';

// setup basic lesson info
$json_object = array();
$json_object['lesson'] = array(); 
$json_object['lesson']['name'] = $llname;
$json_object['lesson']['graded'] = $graded;

// create local directory to store stuff
// mkdir($llname);

// find number of pages and types
$markers=$dom->getElementsByTagName('page');
$numpages = $markers->length;

// create page elements
$json_object['lesson']['page_elements'] = array();

// create table of contents
$section_table = array();
$section_index = 0;
$name_index = 0;

$replacement = '';

//Loop through childNodes
for ($i=0; $i<$numpages; $i++) {

    $marker = $markers->item($i);
	// find page title, type, content
	$title = $marker->getElementsByTagName('title')->item(0)->textContent;
	$qtype = $marker->getElementsByTagName('qtype')->item(0)->textContent;
	$content = $marker->getElementsByTagName('contents')->item(0)->textContent;
	$id = $i;
	
	// get answers so we can store them for later
	$answers = $marker->getElementsByTagName('answer');
	
	// determine page type and set to appropriate value
	switch ($qtype) {
        case 1:
            $type = 'ContentBlock';
            $element_type = 'content_block';
            break;
        case 2:
            $type = 'MultichoiceQuestion';
            $element_type = 'multichoice_question';
            break;
        case 9:
            $type = 'PromptedAudioQuestion';
            $element_type = 'prompted_audio_question';
            break;
        case 3: // true/false
            $type = 'TrueFalse';
            break;
        case 4: // short answer
            $type = 'ShortAnswer';
            break;
        case 5: // cloze
            $type = 'Cloze';
            break;
        case 6: // matching
            $type = 'Matching';
            break;
        case 8: //essay
            $type = 'Essay';
            break;
        case 20: // branch table // table of contents
            $type = 'BranchTable';
            $element_type = 'branch_table';
            break;
        case 21: // end branch
            $type = 'EndBranch';
            $element_type = 'end_branch';
            break;
        case 30: // cluster
            $type = 'Cluster';
            break;
        case 31: // endcluster
            $type = 'delete';
            // get out of function
            break;
	}
	
	// switch MC pages with no answers to Content Pages.
    if ($type == 'MultichoiceQuestion') {
        if (empty($answers->item(0))) {
            $type = 'ContentBlock';
            $element_type = 'content_block';
        }
    }
	
	// only keep formats language lesson will accept
	$keepformat = array('ContentBlock','PromptedAudioQuestion','BranchTable','EndBranch','delete');
	if (!in_array($type,$keepformat)) {
	    $instructions = $_POST['page'.$id];
	    if ($instructions == 'delete') {
	        $type = 'delete';
	    } elseif ($instructions == 'content') {
	        $type = 'ContentBlock';
            $element_type = 'content_block';
        } else {
            $type = 'PromptedAudioQuestion';
            $element_type = 'prompted_audio_question';
        }
	}
	
	// get section names from table of contents - should be first page, if at all
	if ($type == 'BranchTable') {
	    foreach ($answers as $section) {
	        $name_index++;
	        $section_title = $section->getElementsByTagName('answer_text')->item(0)->textContent;
	        //change spaces and / to _ in name
            $section_title = preg_replace('/\s|\//', '_', $section_title);
            $section_title = str_replace(',',$replacement,$section_title);
            $section_title = $title.'_'.$section_title;
	        $section_name = $llname . '_' . $name_index . '_' . $section_title;
	        $section_table[] = $section_name;
	        mkdir($course_dir.'/'.$section_name);
	    }
	    // create empty json text file
        $json_file = $section_table[0] . '.json';

        // setup basic lesson info
        $json_object = array();
        $json_object['lesson'] = array(); 
        $json_object['lesson']['name'] = $section_table[0];
        $json_object['lesson']['graded'] = $graded;
        
        // create page elements
        $json_object['lesson']['page_elements'] = array();
	}
	
	// if not at end of lesson, create save previous json file and create next one
	elseif ($type == 'EndBranch') {
        if ($section_index < count($section_table)-1) {
            // encode in json
            $json_contents = json_encode($json_object);
            // output
            file_put_contents($course_dir .'/'. $section_table[$section_index] .'/'. $json_file, $json_contents, FILE_USE_INCLUDE_PATH);
        
            echo 'Processed <b>' . $section_table[$section_index] . '</b>!<br><br>';
        
            // point to next section
            $section_index ++;
        
            // create empty json text file
            $json_file = $section_table[$section_index] . '.json';

            // setup basic lesson info
            $json_object = array();
            $json_object['lesson'] = array(); 
            $json_object['lesson']['name'] = $section_table[$section_index];
            $json_object['lesson']['graded'] = $graded;
            
            // create page elements
            $json_object['lesson']['page_elements'] = array();
        }     
	}
	
	// collect audio and image files from other pages
	else {
	
	    // if first page is not a table of contents, should have no sections
	    if ($id === 0) {
	        
	        mkdir($course_dir.'/'.$llname);
	    
	        $section_table[] = $llname;
	        
	        // create empty json text file
            $json_file = $llname . '.json';

            // setup basic lesson info
            $json_object = array();
            $json_object['lesson'] = array(); 
            $json_object['lesson']['name'] = $llname;
            $json_object['lesson']['graded'] = $graded;
            
            // create page elements
            $json_object['lesson']['page_elements'] = array();
	    }
	
        // find prompt audio files
        
        // are there any references to Prompt before an audio link?
        $pattern = '#(<|&lt;)a href=[^>]*(>|&gt;)(audio|prompt|dialogue|instructions):?(<|&lt;)\/a(>|&gt;)#i';
        preg_match($pattern,$content,$matches);
    
        // if not, check for audio links with link names
        if (empty($matches)) {
            $pattern = '#(prompt|dialogue).*(<|&lt;)a href=.*\.mp3"(>|&gt;)(<|&lt;)\/a(>|&gt;)(?=(<|&lt;)br)#i';
            preg_match($pattern,$content,$matches);
            $prompt_pattern = '#(prompt|dialogue).*(?=(<|&lt;)a href=)#i';
            if (!empty($matches)) {
                $matches[0] = preg_replace($prompt_pattern,$replacement,$matches[0]);
            }
        }
    
        // check if there are matches, skip next stuff if not
        if (!empty($matches)) {
    
            $prompt_audio = $matches[0];
    
            // replace audio link with nothing and trim for contents whitespace
            $content = trim(str_replace($prompt_audio,$replacement,$content));
    
            // strip out the link tag leaving only the path
            $start_position = strrpos($prompt_audio,'http');
            $prompt_audio = substr($prompt_audio,$start_position);
            $position = strrpos($prompt_audio,'.mp3', -1);
            $prompt_audio = urldecode(substr($prompt_audio,0,$position+4));
            $prompt_file_name = ltrim(strrchr($prompt_audio,'/'), '/');

            // make subfolder and pull in file
            $element_dir = $course_dir .'/'. $section_table[$section_index] .'/'. $i;
            mkdir($element_dir);
        
            // URLs sometimes include a moodle domain, which will be protected
            // get rid of moodle domain to replace with substitute domain
            $prompt_audio = str_replace('https://moodle.carleton.edu/'.$domain,$replacement,$prompt_audio);
            $prompt_audio = str_replace('https://moodle2013-14.carleton.edu'.$domain,$replacement,$prompt_audio);
            $prompt_audio = str_replace('https://moodle.carleton.edu',$replacement,$prompt_audio);
            $prompt_audio = str_replace('https://moodle2013-14.carleton.edu',$replacement,$prompt_audio);
            $prompt_audio = str_replace('https:/moodle.carleton.edu/'.$domain,$replacement,$prompt_audio);
            $prompt_audio = str_replace('https:/moodle2013-14.carleton.edu'.$domain,$replacement,$prompt_audio);
            $prompt_audio = str_replace('https:/moodle.carleton.edu',$replacement,$prompt_audio);
            $prompt_audio = str_replace('https:/moodle2013-14.carleton.edu',$replacement,$prompt_audio);
        
            // if manually uploaded file, must get from files.xml
            $pattern_plugin = "#@@PLUGINFILE@@#";
            preg_match($pattern_plugin, $prompt_audio, $matches_plugin);
            if (!empty($matches_plugin)) {
                $prompt_hash = $filehashes[$prompt_file_name];
                $prompt_path = $path . '/files/' . substr($prompt_hash,0,2) . '/' . $prompt_hash;
                file_put_contents($element_dir . '/' . $prompt_file_name, fopen($prompt_path, 'r'));
            }
            // otherwise get file from provided domain
            else {
                file_put_contents($element_dir . '/' . $prompt_file_name, fopen($domain . $prompt_audio, 'r'));
            }
            
            // replace path with new path relative to json file
            $prompt_audio = str_replace($prompt_audio,$i.'/'.$prompt_file_name,$prompt_audio);
        }
        
        // check for response audio files
        $pattern2 = '#(<|&lt;)a href=[^<]*(<|&lt;)\/a(>|&gt;)#i';
        preg_match($pattern2,$content,$matches2);
    
        // check if there are matches, skip next if not
        if (!empty($matches2)) {
        
            $response_audio = $matches2[0];
        
            // replace audio link with nothing and trim for contents whitespace
            $content = trim(str_replace($response_audio,$replacement,$content));

            // strip out link tag and leave only path to file
            $start_position = strrpos($response_audio,'http');
            $response_audio = substr($response_audio,$start_position);
            $position = strrpos($response_audio,'.mp3',-1);
            $response_audio = urldecode(substr($response_audio,0,$position+4));
            $response_file_name = ltrim(strrchr($response_audio,'/'), '/');
        
            // check for subfolder, make one if necessary, put response file there
            $element_dir = $course_dir .'/'. $section_table[$section_index] .'/'. $i;
            if (!file_exists($element_dir)) {
                mkdir($element_dir);
            }

            // URLs sometimes include a moodle domain, which will be protected
            // get rid of moodle domain to replace with substitute domain
            $response_audio = str_replace('https://moodle.carleton.edu/'.$domain,$replacement,$response_audio);
            $response_audio = str_replace('https://moodle2013-14.carleton.edu'.$domain,$replacement,$response_audio);
            $response_audio = str_replace('https://moodle.carleton.edu',$replacement,$response_audio);
            $response_audio = str_replace('https://moodle2013-14.carleton.edu',$replacement,$response_audio);
            $response_audio = str_replace('https:/moodle.carleton.edu/'.$domain,$replacement,$response_audio);
            $response_audio = str_replace('https:/moodle2013-14.carleton.edu'.$domain,$replacement,$response_audio);
            $response_audio = str_replace('https:/moodle.carleton.edu',$replacement,$response_audio);
            $response_audio = str_replace('https:/moodle2013-14.carleton.edu',$replacement,$response_audio);

            // if manually uploaded file, must get from files.xml
            $pattern_plugin = "#@@PLUGINFILE@@#";
            preg_match($pattern_plugin, $response_audio, $matches_plugin);
            if (!empty($matches_plugin)) {
                $response_hash = $filehashes[$response_file_name];
                $response_path = $path . '/files/' . substr($response_hash,0,2) . '/' . $response_hash;
                file_put_contents($element_dir . '/' . $response_file_name, fopen($response_path, 'r'));
            }
            // otherwise get file from provided domain
            else {
                file_put_contents($element_dir . '/' . $response_file_name, fopen($domain . $response_audio, 'r'));
            }
        
            // replace path with new path relative to json file
            $response_audio = str_replace($response_audio,$i.'/'.$response_file_name,$response_audio);
        
            // change type to PromptResponseAudioQuestion
            if (!empty($prompt_audio)) {
                $type = 'PromptResponseAudioQuestion';
                $element_type = 'prompt_response_audio_question';
            } else {
//                 $prompt_audio = $response_audio;
//                 unset($response_audio);
            }
        }
        
        // look for images embedded in contents and download
        $pattern3 = '#(<|&lt;)img[^>]*(>|&gt;)#';
    
        if (preg_match_all($pattern3,$content,$matches3)) {
    
            foreach ($matches3[0] as $image_file_link) {


                // strip tags to get only path
//                 $image_file = str_replace('<img src="',$replacement,$image_file_link);
//                 $image_file = str_replace('<img src=\\"',$replacement,$image_file_link);
                $start_position = strrpos($image_file_link,'http');
                $image_file = substr($image_file_link,$start_position);
                
            
                // check if file is encoded in base 64
                $base64_pattern = '#data:(<|&lt;);base64,(.+?)"#';
                if (preg_match($base64_pattern,$image_file,$base64_matches)) {
                    // do nothing - leave base 64 image file in content
                } 
                // otherwise it should be a saved image file
                else {

                    // find the end of the path
                    if (!($position = strrpos($image_file,'.jpg',-1))) {
                        if (!($position = strrpos($image_file,'.png',-1))) {
                            $position = strrpos($image_file,'.gif',-1);
                        }
                    }
            
                    // get the full path
                    $image_file = urldecode(substr($image_file,0,$position+4));

                    // find the name of the file
                    $image_file_name = ltrim(strrchr($image_file,'/'), '/');
        
                    // URLs sometimes include a moodle domain, which will be protected
                    // get rid of moodle domain to replace with substitute domain
                    $image_file = str_replace('https://moodle.carleton.edu/'.$domain,$replacement,$image_file);
                    $image_file = str_replace('https://moodle2013-14.carleton.edu'.$domain,$replacement,$image_file);
                    $image_file = str_replace('https://moodle.carleton.edu',$replacement,$image_file);
                    $image_file = str_replace('https://moodle2013-14.carleton.edu',$replacement,$image_file);
                    $image_file = str_replace('https:/moodle.carleton.edu/'.$domain,$replacement,$image_file);
                    $image_file = str_replace('https:/moodle2013-14.carleton.edu'.$domain,$replacement,$image_file);
                    $image_file = str_replace('https:/moodle.carleton.edu',$replacement,$image_file);
                    $image_file = str_replace('https:/moodle2013-14.carleton.edu',$replacement,$image_file);
                    
        
                    // if manually uploaded file, must get from files.xml
                    $pattern_plugin = "#@@PLUGINFILE@@#";
                    preg_match($pattern_plugin, $image_file, $matches_plugin);
                    if (!empty($matches_plugin)) {
                        $image_hash = $filehashes[$image_file_name];
                        $image_path = $path . '/files/' . substr($image_hash,0,2) . '/' . $image_hash;
                    }
                    // otherwise get file from provided domain
                    else {
                        $image_path = $domain . $image_file;
                    }
                
                    $image_data = file_get_contents($image_path);
                    $image_base64 = base64_encode($image_data);
                    $new_file_link = '<img src="data:<;base64,'.$image_base64.'" alt="">';
                
                    // replace path with new path relative to the json file
                    $content = trim(str_replace($image_file_link,$new_file_link,$content));
                }
            }
        }
    
    	//remove prompt/reponse text
    	$pattern_prompt_text = '#(<|&lt;)b(>|&gt;)(response:|prompt:) ?(<|&lt;)\/b(>|&gt;)#i';
    	$content = preg_replace($pattern_prompt_text, $replacement, $content);
    
        //clean out empty tags
        //$pattern4 = '#(<|&lt;)[^\/](\s?)*(\d?)*(\s?)(>|&gt;)(<|&lt;)\/*[a-z]+(\d?)(>|&gt;)?#i';
        $pattern4 = '#(<|&lt;)[^\/](\s?)*(\d?)*(\s?)(>|&gt;)(<br>|<br />|<br/>)*(<|&lt;)\/[a-z]+(\d?)(>|&gt;)?#i';
        $content = preg_replace($pattern4, $replacement, $content);
        
        // Find abandoned <br> tags at the end of contents and strip them
        $pattern5 = '#(<br>|<br />|<br/>)+$#';
        $content = preg_replace($pattern5, $replacement, $content);
    
        // enter item into array unless it's marked for delete
        if ($type !== 'delete') {
            // make a new item entry
            $this_question[$element_type] = array();
        
            // this page's info
            $this_question[$element_type]['id'] = $id;
            $this_question[$element_type]['type'] = $type;
            $this_question[$element_type]['title']= $title;
            $this_question[$element_type]['content'] = $content;
            if ($type == 'ContentBlock') {
                if (!empty($prompt_audio)) {
                    $this_question[$element_type]['audio'] = $prompt_audio;
                } elseif (!empty($response_audio)) {
                    $this_question[$element_type]['audio'] = $response_audio;
                }
            } else {
                if (!empty($prompt_audio)) {
                    $this_question[$element_type]['prompt_audio'] = $prompt_audio;
                }
                if (!empty($response_audio)) {
                    $this_question[$element_type]['response_audio'] = $response_audio;
                }
            }
        
            $json_object['lesson']['page_elements'][] = $this_question;
        }
        // clean up variables
        unset($matches);
        unset($matches2);
        unset($matches3);
        unset($matches_plugin);
        unset($moodle_domain);
        unset($type);
        unset($element_type);
        unset($prompt_audio);
        unset($response_audio);
        unset($image_file);
        unset($this_question);
        unset($position);
    }
}
	
// save last json file
// encode in json
$json_contents = json_encode($json_object);
// output
file_put_contents($course_dir .'/'. $section_table[$section_index] .'/'. $json_file, $json_contents, FILE_USE_INCLUDE_PATH);

echo 'Processed <b>' . $section_table[$section_index] . '</b>!<br><br>';


?>