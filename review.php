<html>
    <body>
        <form name='review LL' method='post' action='convert.php' enctype='multipart/form-data'>
        <?php
        /*
        * Creates a form to ask directions for pages that
        * are not content or audio
        */

        $file = $_FILES['file']['tmp_name'];
        $domain = $_POST['domain'];
        $path = $_POST['path'];

        $dom = new DOMDocument();

        $dom->load($file);

        // find number of pages and types
        $markers=$dom->getElementsByTagName('page');
        $numpages = $markers->length;

        $title = $dom->getElementsByTagName('name')->item(0)->textContent;
        echo '<br><h2>'.$title.'</h2><br>';

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
                case 20: // branch table
                    $type = 'BranchTable';
                    break;
                case 21: // end branch
                    $type = 'EndBranch';
                    break;
                case 30: // cluster
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
            
            $keepformat = array('ContentBlock','PromptedAudioQuestion','BranchTable','EndBranch','delete');
            if (!in_array($type,$keepformat)) {
                echo '<p>Page number '.$id.', &nbsptitled <b>"'.$title.',"</b> &nbspis type <b>'.$type.'</b></p>';
                echo '<input type="radio" name="page'.$id.'" value="delete" checked>Delete &nbsp&nbsp';
                echo '<input type="radio" name="page'.$id.'" value="content">Change to content page &nbsp&nbsp';
                echo '<input type="radio" name="page'.$id.'" value="audio">Change to audio page &nbsp&nbsp';
                echo '<br><br>';
            }
        }
        
        echo '<br><b> Upload the languagelesson XML file again </b><br>';
        echo '<label for="file">languagelesson.xlm: </label>';
        echo '<input type="file" name="file" id="file"><br>';
        echo '<input type="hidden" name="domain" value="'.$domain.'">';
        echo '<input type="hidden" name="path" value="'.$path.'">';
        echo '<br><input type="submit" name="submit" value="Submit"><br><br><br>';
        ?>
        <form>
    <body>
<html>