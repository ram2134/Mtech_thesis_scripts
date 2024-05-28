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

class Tutor {
	
	const RESULT_SUCCESS = "SUCCESS";
	const RESULT_FAILURE = "FAILURE";
	
	const VERDICT_ACCEPTED = "ACCEPTED";
	const VERDICT_WRONGANSWER = "WRONG_ANSWER";
	const VERDICT_TIMEDOUT = "TIMED_OUT";
	const VERDICT_ERROR = "ERROR";
	
	const EXECUTION_TYPE_TESTCASE = 0;
	const EXECUTION_TYPE_CUSTOM = 1;
	
	const SAVE_TYPE_AUTO = 0;
	const SAVE_TYPE_MANUAL = 1;
	const SAVE_TYPE_SUBMIT = 2;
	const SAVE_TYPE_COMPILE = 3;
	const SAVE_TYPE_PASTE = 4;
	
	public static function instance() {
		return new self();
	}
	
	public function compileUnspecific($code, $undelayed = false, $env) {
		
		$identifier = Session::getUserID();
		
		if(!$undelayed) {
			$config = NoSQL::fetchOne("its.configs", array('name'=>'delays'));
			$config = $config->settings;
			$delay = intval($config->compilation);
			sleep(intval($delay / 1000));
		}
	        
	        	
		$output = Engine::instance()->compile($identifier, $code, $env);
		
		return $output;
	}
	
	public function compileSpecific($assignment_id, $code, $env) {
		
		// COMPILATION DELAY
		
		$config = NoSQL::fetchOne("its.configs", array('name'=>'delays'));
		$config = $config->settings;
		$delay = intval($config->compilation);
		sleep(intval($delay / 1000));
		
		// FETCH ASSIGNMENT
		
		$assignment = R::load('assignment', $assignment_id);
		$account    = R::load('account',    $assignment->user_id);
		
		$identifier = 'e' . $assignment->event_id . '_' . $assignment_id;
		
		
		//Added by Akanksha 

                //Engine instance

		$Engine_obj =new  Engine();

                if($env=='sql')
                {
                        
                       
                        $problem = R::load('problem',$assignment->problem_id);

			$schema_id = $problem->schema_id;
			$query = $problem->solution;

		        $query = base64_decode($query);

		

			$xdata_schema = R::load('xdata_schema',$schema_id);

			
			$name = $xdata_schema->name;
			$schema_file = $xdata_schema->schema_file;
			$sample_data = $xdata_schema->sample_data;
		

			$Engine_obj->setSchema($schema_file);
			$Engine_obj->setSampleData($sample_data);
			$Engine_obj->setQuery($query);
		}

                        

                //Added by Akanksha Ends


               
	        //Added by Akanksha, changed Engine::instance() to $Engine_obj in below line

		$result = $Engine_obj->compile($identifier, $code, $env);
		
		// SAVE CODE
		
		$code_id = Logger::instance()->saveCode($assignment_id, $code, $this::SAVE_TYPE_COMPILE);
		
		// LOG
		
		$compilation_id = Logger::instance()->logCompilationResult(
			$assignment_id, 
			$code_id, 
			$result['raw'], 
			$result['success']
		);
		
		$result['code_id'] = $code_id;
		
		// POST-HOOK: Only if compilation unsuccessful
		// Since most tools aren't interested in codes which compiles
		// Disabling this check, in case tools are interested in compiling code. Pass success flag instead
		// if(!$result['success']) 
		{
			$hook = Tool::instance()->invoke("compile", "post", array( 
				'env'=>$env,
				'assignment_id'=>$assignment_id,
				'code_id'=>$code_id,
				'code'=>base64_encode($code),
				'compiler_output'=>$result['raw'],
				'compiler_success'=>$result['success'],
				'section'=>$account->section,
				'roll'=>$account->roll
			));

			$result['plugins'] = $hook;
		}
		
		return $result;
	       
	}
	
	public function executeUnspecific($code, $testcase, $undelayed = true, $env) {
		
		$identifier = Session::getUserID();
		
		$config = NoSQL::fetchOne("its.environments", array('name'=>$env));
		
		$executable = sprintf("%s.%s", $identifier, $config->binary_ext);
		
		if(!$undelayed) {
			$config = NoSQL::fetchOne("its.configs", array('name'=>'delays'));
			$config = $config->settings;
			$delay = intval($config->execution);
			sleep(intval($delay / 1000));
		}
		
		$output = Engine::instance()->compile($identifier, $code, $env);
		
		if($output['success'])
			$output = Engine::instance()->execute($output['executable'], $testcase, $env);
		else $output = null;
		
		return $output;
	}
	
	public function executeSpecific($assignment_id, $testcase, $env) {
		
		$config = NoSQL::fetchOne("its.configs", array('name'=>'delays'));
		$config = $config->settings;
		$delay = intval($config->execution);
		sleep(intval($delay / 1000));
		
		$user_id = Session::getUserID();
		
		$assignment = R::load('assignment', $assignment_id);
		
		$rows = R::getAssocRow("SELECT id,contents FROM code WHERE assignment_id=? ORDER BY save_time DESC LIMIT 1", array($assignment_id));
		$code = base64_decode($rows[0]['contents']);
		$code_id = $rows[0]['id'];
		
		$identifier = 'e' . $assignment->event_id . '_' . $assignment_id;
		
		$compilation = Engine::instance()->compile($identifier, $code, $env);
		if(!isset($compilation['executable']))
			return array('status'=>'ER', 'output'=>'');
		$executable = $compilation['executable'];
		
		$result = Engine::instance()->execute($executable, $testcase, $env);
		
		if($result === FALSE)
			$result = array('status'=>'ER', 'output'=>'');
		else {
			Logger::instance()->logExecutionResult(
				$assignment_id, 
				$result['status'], 
				$testcase, 
				$result['output']
			);
		}
		
		// POST-HOOK
		
		$hook = Tool::instance()->invoke("execute", "post", array(
			'env'=>$env, 
			'assignment_id'=>$assignment_id,
			'code_id'=>$code_id,
			'code'=>base64_encode($code),
			'test_case'=>$testcase
		));
		
		$result['plugins'] = $hook;
		
		return $result;
	  
	}
	
	public function interpretUnspecific($code, $input, $undelayed = true, $env) {
		
		$identifier = Session::getUserID();
		
		if(!$undelayed) {
			$config = NoSQL::fetchOne("its.configs", array('name'=>'delays'));
			$config = $config->settings;
			$delay = intval($config->execution);
			sleep(intval($delay / 1000));
		}
		
		$output = Engine::instance()->interpret($identifier, $code, $input, $env);
		
		return $output;
	}
	
	public function interpretSpecific($assignment_id, $code, $input, $env) {
		
		$config = NoSQL::fetchOne("its.configs", array('name'=>'delays'));
		$config = $config->settings;
		$delay = intval($config->execution);
		sleep(intval($delay / 1000));
		
		$user_id = Session::getUserID();
		
		$assignment = R::load('assignment', $assignment_id);
		
		$identifier = 'e' . $assignment->event_id . '_' . $assignment_id;
		
		$result = Engine::instance()->interpret($identifier, $code, $input, $env);
		
		if($output === FALSE)
			$result = array('status'=>'ER');
		else {
			Logger::instance()->logExecutionResult(
				$assignment_id, 
				$output['status'], 
				$input, 
				$output['output']
			);
		}
		
		// POST-HOOK
		
		$hook = Tool::instance()->invoke("execute", "post", array(
			'env'=>$env, 
			'assignment_id'=>$assignment_id,
			'code_id'=>$code_id,
			'code'=>base64_encode($code),
			'test_case'=>$testcase
		));
		
		$result['plugins'] = $hook;
		
		return $output;
	}
	
	public function evaluateUnspecific($assignment_id, $type = 1, $code = null, $undelayed = true) {
		
		if(!$undelayed) {
			$config = NoSQL::fetchOne("its.configs", array('name'=>'delays'));
			$config = $config->settings;
			$delay = intval($config->evaluation);
			sleep(intval($delay / 1000));
		}
		
		$defaulter = false;
		$query = "SELECT problem_id,is_submitted,(SELECT CASE WHEN is_submitted=1 THEN 
				(SELECT contents FROM code WHERE code.id=submission) ELSE 
				(SELECT contents FROM code WHERE assignment_id=assignment.id ORDER BY save_time DESC LIMIT 1) END) AS code, 
				(SELECT env FROM problem WHERE id=problem_id) AS env
				FROM assignment WHERE id=:id";
		$rows = R::getAssocRow($query, array(':id'=>$assignment_id));
		
		if(count($rows)) {
			$assignment = $rows[0];
			$env = $assignment['env'];
			$config = NoSQL::fetchOne("its.environments", array('name'=>$env));
			$query = "SELECT id,type,visibility,input,output FROM test_case WHERE problem_id=:id AND is_deleted=0 AND type>=:type";
			$testCases = R::getAssocRow($query, array(':id'=>$assignment['problem_id'], ':type'=>$type));
			if(!count($testCases)) {
				return false;
			}
			$identifier = null;
			$file_source = null;
			while(!$file_source) {
				$identifier = 'admin_' . generate_random_string();
				$file_source = sprintf('%s.%s', $identifier, $config->source_ext);
				if(file_exists(PATH_APPDATA.$file_source)) $file_source = null;
			}
			if($config->compile) {
				// Compile
				if($code === null) $code = base64_decode($assignment['code']);
				$output = Engine::instance()->compile($identifier, $code, $env);
				#if(file_exists(PATH_APPDATA.$file_source)) unlink(PATH_APPDATA.$file_source);
				if(!$output['success']) { return array('compilation'=>$output); }
				$executable = $identifier . '.' . $config->binary_ext;
			} else {
				$output = null;
			}
			// Evaluate
			$results = array();
			$verdict = $this::VERDICT_ACCEPTED;
			$engine = Engine::instance();
			foreach($testCases as $test) {
				if(!$config->compile) {
					$actual = $engine->interpret($identifier, $code, $test['input'], $env);
				} else {
					$actual = $engine->execute($executable, $test['input'], $env);
				}
				if($actual === FALSE) {
					$result = array('status'=>'ER');
					$verdict = $this::VERDICT_ERROR;
					$results = array();
					// TODO log error results
					break;
				} else {
					$result = $actual;
					if(trim($test['output']) !== trim($result['output'])) $verdict = $this::VERDICT_WRONGANSWER;
				}
				array_push($results, array(
				'id'=>$test['id'],
				'type'=>$test['type'],
				'visibility'=>$test['visibility'],
				'input'=>$test['input'],
				'expected'=>$test['output'],
				'actual'=>$result
				));
			}
			if(isset($executable) && file_exists(PATH_APPDATA.$executable)) unlink(PATH_APPDATA.$executable);
			return array(
				'compilation'=>$output,
				'evaluation'=>$results,
				'verdict'=>$verdict,
				'defaulter'=>(intval($assignment['is_submitted']) === 0)
			);
		} else {
			return null;
		}
	}

	public function evaluateSpecific($assignment_id) {
		
		$config = NoSQL::fetchOne("its.configs", array('name'=>'delays'));
		$config = $config->settings;
		$delay = intval($config->evaluation);
		sleep(intval($delay / 1000));
		
		$user_id = Session::getUserID();
		
		$assignment = R::load('assignment', $assignment_id);
		
		$rows = R::getAssocRow("SELECT id,contents FROM code WHERE assignment_id=? ORDER BY save_time DESC LIMIT 1", array($assignment_id));
		$code = base64_decode($rows[0]['contents']);
		$code_id = $rows[0]['id'];
		
		$rows = R::getAssocRow("SELECT env FROM problem WHERE id=(SELECT problem_id FROM assignment WHERE id=?)", array($assignment_id));
		$env = $rows[0]['env'];
		
		$config = NoSQL::fetchOne("its.environments", array('name'=>$env));
		
		$identifier = 'e' . $assignment->event_id . '_' . $assignment_id;
		
		$engine = Engine::instance();
		
		$compilation = $engine->compile($identifier, $code, $env);
		if(!isset($compilation['executable'])) {
			return array(
				'verdict'=>$this::VERDICT_ERROR,
				'results'=>array('status'=>'ER', 'output'=>''),
				'invisible'=>array('passed'=>0, 'total'=>0),
				'feedback'=>null
			);
		} 
		$executable = $compilation['executable'];
		
		// Visible test cases.
		$query = "SELECT id AS test_id,input,output 
			FROM test_case
			WHERE problem_id=(SELECT problem_id FROM assignment WHERE id=:id)
			AND is_deleted=0 AND visibility=1";
		$testcases = Helper::cacheData($query, array(':id'=>$assignment_id));
		
		$evaluation = array();
		
		$results = array();
		$verdict = $this::VERDICT_ACCEPTED;
		
		foreach($testcases as $testcase) {
			$local_verdict = $this::VERDICT_ACCEPTED;
			if(!$config->compile) {
				$actual = $engine->interpret($identifier, $code, $testcase['input'], $env);
			} else {
				$actual = $engine->execute($executable, $testcase['input'], $env);
			}
			if($actual === FALSE) {
				$result = array('status'=>'ER', 'output'=>'');
				$verdict = $this::VERDICT_ERROR;
				$results = array();
				// TODO log error results
				break;
			} else {
				$result = $actual;
				if(trim($testcase['output']) !== trim($result['output'])) {
					$verdict = $this::VERDICT_WRONGANSWER;
					$local_verdict = $this::VERDICT_WRONGANSWER;
				}
			}
			array_push($evaluation, array(
				'id'=>$testcase['test_id'],
				'output'=>$result['output'],
				'result'=>$result['status'],
				'verdict'=>$local_verdict
			));
			array_push($results, array(
				'id'=>$testcase['test_id'],
				'input'=>$testcase['input'],
				'expected'=>$testcase['output'],
				'actual'=>$result
			));
		}
		
		// Invisible test cases.
		$query = "SELECT id AS test_id,input,output 
			FROM test_case
			WHERE problem_id=(SELECT problem_id FROM assignment WHERE id=:id)
			AND is_deleted=0 AND visibility=0 AND type=1";
		$testcases = Helper::cacheData($query, array(':id'=>$assignment_id));
		
		$passed = 0;
		
		foreach($testcases as $testcase) {
			$local_verdict = $this::VERDICT_ACCEPTED;
			if(!$config->compile) {
				$actual = $engine->interpret($identifier, $code, $testcase['input'], $env);
			} else {
				$actual = $engine->execute($executable, $testcase['input'], $env);
			}
			if($actual === FALSE) {
				$result = array('status'=>'ER', 'output'=>'');
				$verdict = $this::VERDICT_ERROR;
				// TODO log error results
				break;
			} else {
				$result = $actual;
				if(trim($testcase['output']) !== trim($result['output'])) {
					$verdict = $this::VERDICT_WRONGANSWER;
					$local_verdict = $this::VERDICT_WRONGANSWER;
				} else {
					$passed++;
				}
			}
			array_push($evaluation, array(
				'id'=>$testcase['test_id'],
				'output'=>$result['output'],
				'result'=>$result['status'],
				'verdict'=>$local_verdict
			));
		}
		
		Logger::instance()->logEvaluationResult(
			$assignment_id,
			$code_id, 
			$evaluation
		);
		
		$result = array(
			'verdict'=>$verdict, 
			'results'=>$results,
			'invisible'=>array('passed'=>$passed, 'total'=>count($testcases))
		);
		
		// POST-HOOK
		
		$hook = Tool::instance()->invoke("evaluate", "post", array(
			'env'=>$env, 
			'assignment_id'=>$assignment_id,
			'code_id'=>$code_id,
			'code'=>base64_encode($code),
			'verdict'=>$verdict
		));
		
		$result['plugins'] = $hook;
		
		return $result;
	}
	
}

?>
