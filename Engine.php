<?php
/**
 * Copyright (c) 2014 Rajdeep Das.
 * All rights reserved.
 *
 * Usage of this program and the accompanying materials in any form
 * without prior permission from the owner is strictly prohibited.
 *
 * Author(s): Rajdeep Das <rajdeepd@iitk.ac.in>
 */

class Engine {
	
	const VERDICT_ACCEPTED = "ACCEPTED";
	const VERDICT_WRONGANSWER = "WRONG_ANSWER";
	const VERDICT_TIMEDOUT = "TIMED_OUT";
	const VERDICT_ERROR = "ERROR";
	
	private $STATUS_KNOWN = array('OK', 'RT', 'RF', 'TL', 'AT', 'ML');
	private $STATUS_UNKNOWN = array('PD', 'OL', 'IE', 'BP');
       // Added by Akanksha
        private $SCHEMA_FILE = NULL;
        private $SAMPLE_DATA = NULL;
        private $Query = NULL;
        /**
         * Updates schema for sql env
         * 
         * 
         */
	public function setSchema($schema)
	{
	   $this->SCHEMA_FILE = $schema;
	}


        /**
         * Updates sampledata for sql env
         * 
         *
         */
	public function setSampleData($sampledata){

            $this->SAMPLE_DATA = $sampledata;
	}	

         /**
         * Updates correct queries for sql env*/
	public function setQuery($query)
	{
	   $this->Query = $query;
	}
	//Added by Akanksha Ends
	
	/**
	 * Returns an instance of this.
	 * 
	 * @return Engine
	 */
	public static function instance() {
		return new self();
	} 
	
	/**
	 * Compiles the code and returns the result. The identifier is the 
	 * name of the unique identifier for a code version. The source
	 * file is not deleted after compilation and the executable is kept.
	 * The name of the executable is <build_name>.out.
	 * 
	 * @param string $identifier
	 * @param string $code
	 * @return array
	 */
	public function compile($identifier, $code, $env="C") { 
		
		// FINAL RESULT ARRAY

		
		$result = array();

		//Added by Akanksh->edited $env in parameters,removed ="c"
		  $result['env']=$env;
		 

               //Added by Akanksha end's

		// FETCH COMPILATION CONFIGURATION
		
		$config = NoSQL::fetchOne("its.environments", array('name'=>$env));
		
		$cmd = str_replace("%s", $identifier, $config->cmd_compile);
		
		$file_source = sprintf("%s.%s", $identifier, $config->source_ext); //source file creation,which is query.txt and mutant.txt 
		$file_executable = sprintf("%s.%s", $identifier, $config->binary_ext);// No binary executable needed for xdata

	        	
		file_put_contents(PATH_APPDATA.$file_source, $code); //query will be written to file stored at location PATH_APPDATA.$file_source
		
		
		//Added by Akanksha
		
		//$result['schema_file']=$this->SCHEMA_FILE;
		if(isset($this->SCHEMA_FILE) && isset($this->SAMPLE_DATA)&&isset($this->Query))
		{
		   
		    $file_schema = $identifier."_SchemaFile.sql";
		    $file_sampledata = $identifier."_SampleData.sql";
		    $file_query = $identifier."_query.sql";    
		    file_put_contents(PATH_APPDATA.$file_schema,$this->SCHEMA_FILE);

		    file_put_contents(PATH_APPDATA.$file_sampledata,$this->SAMPLE_DATA);
		   
		    file_put_contents(PATH_APPDATA.$file_query,$this->Query);


		}

		

                //Added by Akanksha ends

                // EXECUTE COMPILATION COMMAND
		
		chdir(BASE_DIR.PATH_APPDATA); //changing the correct working directory
		
		$descriptors = array(
			0 => array('pipe', 'r'),  // stdin
			1 => array('pipe', 'w'),  // stdout
			2 => array('pipe', 'w')   // stderr
		);
		
		$process = proc_open('exec ' . $cmd, $descriptors, $pipes); //the .sh file should contain commands to connect to postgres as well for compiling,if 
		  //compilation done from terminal
		
		if(!is_resource($process)) {
			chdir(BASE_DIR);
			return FALSE;
		}
		
		$buffer = stream_get_contents($pipes[1]);
		$errors = trim(stream_get_contents($pipes[2]));
		
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		
		proc_close($process);
		
		if($errors && strpos($errors, 'error:') !== FALSE) {
			$compiled = false;
		} else {
			$compiled = true;
		}
		
		$output = $errors;
		
		//if(file_exists($file_source) and $env != 'Python')
		//	unlink($file_source);
	
		chdir(BASE_DIR);
		
		// COMPILE RESULTS
		
		if($compiled) {
			$result['success'] = true; 
			$result['executable'] = $file_executable;  
			$result['raw'] = $output; 
		} else {
			$result['success'] = false; 
			$result['raw'] = $output;
		}
		
		$result['processed'] = $this->processCompilerMessages($output);
		
		return $result;
	}
	
	/**
	 * Executes a pre-compiled file and returns the results. The execu-
	 * table file is not deleted and is kept for future re-use. The 
	 * testcase is the input to the program via stdin. Runtime analysis
	 * is also carried out.
	 * 
	 * @param string $executable
	 * @param string $testcase
	 * @return boolean|array
	 */
	public function execute($executable, $testcase, $env = "C", $keep_executable = true) {
		
		// CHECK EXISTENCE OF EXECUTABLE
		
		if(!file_exists(PATH_APPDATA.$executable)) {
			return FALSE;
		}
		
		// FINAL RESULT ARRAY
		
		$result = array();
		
		// FETCH CONFIGURATION
		
		$config = NoSQL::fetchOne("its.configs", array('name'=>'sandbox'));
		$config = $config->settings;
		
		$quota_time = $config->quotas->time;
		$quota_memory = $config->quotas->memory;

		// SETUP SANDBOX IF NOT ALREADY SETUP
	
		if(!file_exists(PATH_APPDATA.'sandbox')) {
			copy(PATH_THIRD_PARTY.'sandbox', PATH_APPDATA.'sandbox');
			chmod(PATH_APPDATA.'sandbox', 0777);
		}
	
		chdir(PATH_APPDATA);
		
		// FETCH ENVIRONMENT
		
		$config = NoSQL::fetchOne("its.environments", array('name'=>$env));
		
		$cmd = str_replace("%s", $executable, $config->cmd_execute);
		$cmd = sprintf("./sandbox %s %s %s", $cmd, $quota_time, $quota_memory);
		
		// EXECUTE PROGRAM
		
		$descriptors = array(
			0 => array('pipe', 'r'),  // stdin
			1 => array('pipe', 'w'),  // stdout
			2 => array('pipe', 'w')   // stderr
		);
	
		$process = proc_open('exec ' . $cmd, $descriptors, $pipes);
	
		if(!is_resource($process)) {
			chdir(BASE_DIR);
			return FALSE;
		}
	
		fwrite($pipes[0], $testcase);
		fclose($pipes[0]);
	
                // FIX for too much outout - truncate the output.
                // a better limit would be, say k times the actual output size.
                // but currently a hardcoded value.
                $maxsz = 100000;
                $buffer = stream_get_contents($pipes[1]);
                $buffer = substr($buffer, 0, $maxsz);

		$status = stream_get_contents($pipes[2]);
	
		fclose($pipes[1]);
		fclose($pipes[2]);
	
		proc_close($process);
		
		if(file_exists($executable) && !$keep_executable)
			unlink($executable);
	
		chdir(BASE_DIR);
			
		// COMPILE RESULTS
		
		if(empty($status)) {
			$result['success'] = false;
			$result['status'] = 'ER';
			$result['output'] = '';
		} else {
			$status = trim($status);
			if(preg_match("/([a-zA-Z]+):([0-9]+):([0-9]+)/", $status)) {
				$results = explode(':', $status);
				$rc = strtoupper($results[0]);
				$cpu = $results[1];
				$mem = $results[2];
				if(in_array($rc, $this->STATUS_KNOWN)) {
					// Something known happened.
					$buffer = preg_replace('/[\x00-\x08\x0B-\x1F\x7F-\xFF]/', '', $buffer);
					$result['success'] = true;
					$result['output'] = $buffer;
					$result['status'] = $rc;
					$result['sandbox'] = $status;
				} else {
					// Something unknown happened.
					file_put_contents(BASE_DIR.'data/exec-errors.log', $status."\n", FILE_APPEND);
					$result['success'] = false;
					$result['status'] = 'ER';
					$result['output'] = '';
				}
			} else {
				file_put_contents(BASE_DIR.'data/exec-errors.log', $status."\n", FILE_APPEND);
				$result['success'] = false;
				$result['status'] = 'ER';
				$result['output'] = '';
			}
		}
	
		return $result;
	}
	
	/**
	 * Interprets a program.
	 */
	public function interpret($identifier, $code, $input, $env = "C") {
		
		// FINAL RESULT ARRAY
		
		$result = array();
		
		// FETCH CONFIG
		
		$config = NoSQL::fetchOne("its.configs", array('name'=>'sandbox'));
		$config = $config->settings;
		
		$quota_time = $config->quotas->time;
		$quota_memory = $config->quotas->memory;
		
		/*if(!file_exists(PATH_APPDATA.'sandbox')) {
			copy(PATH_THIRD_PARTY.'sandbox', PATH_APPDATA.'sandbox');
			chmod(PATH_APPDATA.'sandbox', 0777);
		}*/
		
		$config = NoSQL::fetchOne("its.environments", array('name'=>$env));
		
		$cmd = str_replace("%s", sprintf("%s.%s", $identifier, $config->source_ext), $config->cmd_execute);
		// TODO security vulnerability fix
		//$cmd = sprintf('./sandbox "%s" %s %s', $cmd, $quota_time, $quota_memory);
		
		$file_source = sprintf("%s.%s", $identifier, $config->source_ext);
		file_put_contents(PATH_APPDATA.$file_source, $code);
		chmod(PATH_APPDATA . $file_source, 0777);
		
		// EXECUTE PROGRAM
		
		chdir(BASE_DIR.PATH_APPDATA);
		
		$descriptors = array(
			0 => array('pipe', 'r'),  // stdin
			1 => array('pipe', 'w'),  // stdout
			2 => array('pipe', 'w')   // stderr
		);
		
		$process = proc_open('exec ' . $cmd, $descriptors, $pipes);
		
		if(!is_resource($process)) {
			chdir(BASE_DIR);
			return FALSE;
		}
		
		fwrite($pipes[0], $input);
		fclose($pipes[0]);
		
		$buffer = stream_get_contents($pipes[1]);
		$status = trim(stream_get_contents($pipes[2]));
		
		fclose($pipes[1]);
		fclose($pipes[2]);
		
		proc_close($process);
		
		chdir(BASE_DIR);
		
		// COMPILE RESULTS
		
		if(empty($status)) {
			$result['success'] = true;
			$result['status'] = 'OK';
			$result['output'] = trim($buffer);
			$result['sandbox'] = $status;
		} else {
			$result['success'] = false;
			$result['status'] = 'CT';
			$result['sandbox'] = $status;
		} 
		
		return $result;
	}

	private function processCompilerMessages($output) {
		
		$lines = array_filter(explode("\n", $output));
		
		$errors = array();
		
		/**
		 * Each valid message consists of 4 parts:
		 * [0] Line number
		 * [1] Column number
		 * [2] Message type (error/warning)
		 * [3] Message text
		*/
		foreach($lines as $line) {
			$line = trim(substr($line, strpos($line, ':') + 1));
			if(is_numeric(substr($line, 0, 1))) {
				$parts = explode(':', $line);
				if(count($parts) === 4) array_push($errors, $parts);
			}
		}
		
		$feedback = array();

		// TODO omitting those errors whose templates aren't present			
		foreach($errors as $error) {
			$line = trim($error[0]);
			$position = trim($error[1]);
			$type = trim($error[2]);
			$message = trim($error[3]);
			
			if($type === 'note') $type = 'info';
			
			array_push($feedback, array(
				'type'=>$type,
				'line'=>$line,
				'position'=>$position,
				'feedback'=>$message
			));
		}
		
		return $feedback;
	}
}

?>
