<?php
       
// ===========================================================================// 
// ! Client                                                                   //
// ===========================================================================//

# Create our gearman client
$gmclient= new GearmanClient(); 

# add the default job server
$gmclient->addServer('10.170.102.159');

# add a task to perform the "reverse" function on the string "Hello World!"
$gmclient->addTask("sleep5", "Hello World!", null, "1"); 

# add another task to perform the "reverse" function on the string "!dlroW olleH"
$gmclient->addTask("sleep10", "!dlroW olleH", null, "2"); 

# set a function to be called when the work is complete
$gmclient->setCompleteCallback("complete"); 

# run the tasks
$gmclient->runTasks(); 

function complete($task) 
{ 
  print "COMPLETE: " . $task->unique() . ", " . $task->data() . "\n"; 
}

?>  
   

<?php

// ===========================================================================// 
// ! Worker                                                                   //
// ===========================================================================//

echo "Starting\n";

# Create our worker object.
$gmworker= new GearmanWorker();

# Add default server (localhost).
$gmworker->addServer('10.170.102.159');

# Register function "reverse" with the server.
$gmworker->addFunction("sleep5", "sleepFive");

# Register function "reverse" with the server.
$gmworker->addFunction("sleep10", "sleepTen");

print "Waiting for job...\n"; 

while($gmworker->work())
{
	if ($gmworker->returnCode() != GEARMAN_SUCCESS)
	{
		echo "return_code: " . $gmworker->returnCode() . "\n";
		break;
	} 
} 

// Sleep for 5 seconds
function sleepFive()
{
	echo "\nSleep 5 called\n";
	
	sleep(2);
	
	return "\nslept for 5 seconds\n";
} 

// Sleep for 5 seconds
function sleepTen()
{   
	echo "\nSleep 10 called\n";
	
	sleep(4);
	
	return "\nslept for 10 seconds\n";
}

function reverse_fn($job)
{
  echo "Received job: " . $job->handle() . "\n";

  $workload = $job->workload();
  $workload_size = $job->workloadSize();

  echo "Workload: $workload ($workload_size)\n";

  # This status loop is not needed, just showing how it works
  for ($x= 0; $x < $workload_size; $x++)
  {
    echo "Sending status: " . ($x + 1) . "/$workload_size complete\n";
    $job->sendStatus($x+1, $workload_size);
    $job->sendData(substr($workload, $x, 1));
    sleep(1);
  }

  $result = strrev($workload);
  echo "Result: $result\n";

  # Return what we want to send back to the client.
  return $result;
}

?>
