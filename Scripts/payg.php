<?PHP
	/****************************************************************/
	/* Pay as you go by Cobi released under the GPL.                */
	/****************************************************************/

	/************************* CONFIGURATION ************************/
	/* Memory */
	$mem01		= "0.0005";	/* charge for 1 process using 0.0%-1.0% memory each interval */
	$mem12		= "0.001";	/* charge for 1 process using 1.0%-2.0% memory each interval */
	$mem23		= "0.002";	/* charge for 1 process using 2.0%-3.0% memory each interval */
	$mem34		= "0.003";	/* charge for 1 process using 3.0%-4.0% memory each interval */
	$mem4		= "0.004";	/* charge for 1 process using 4.0+% memory each interval */
	/* CPU */
	$cpu01		= "0";		/* charge for 1 process using 0.0%-1.0% cpu each interval */
	$cpu12		= "0";		/* charge for 1 process using 1.0%-2.0% cpu each interval */
	$cpu23		= "0";		/* charge for 1 process using 2.0%-3.0% cpu each interval */
	$cpu34		= "0";		/* charge for 1 process using 3.0%-4.0% cpu each interval */
	$cpu4		= "0";		/* charge for 1 process using 4.0+% cpu each interval */
	/* Disk */
	$dquota		= "50";		/* how many megabytes can be used before charging */
	$disk		= "0.0001";	/* charge per megabyte over quota per hour */
	/* Sockets */
	$squota		= "25";		/* how many sockets per process can be used before charging (excluding listeners) */
	$socket		= "0.005";	/* charge per hour per process over socket quota */
	/* Bandwidth */
	$bquota		= "0";		/* how many megabytes of bandwidth can be used before charging */
	$bandwidth	= "0.002";	/* charge per megabyte over bandwidth quota */
	/* Misc */
	$suspend	= 0;		/* Suspend account if funds reach 0; 1 = Suspend, 0 = Do nothing. */
	$loging		= 1;		/* Log if set to 1 */
	$logdir		= "/root/payg/logs";
	/* Files */
	$usersf		= "/root/payg/users";
	/* Scripts */
	$suspendscr	= "/root/payg/suspend.sh";
	/* Programs */
	/* Use 'which <program>' to find each one */
	$prg_ps		= "/bin/ps";
	$prg_awk	= "/bin/awk";
	$prg_netstat	= "/bin/netstat";
	$prg_echo	= "/bin/echo";
	$prg_grep	= "/bin/grep";
	$prg_wc		= "/usr/bin/wc";
	$prg_find	= "/usr/bin/find";
	$prg_xargs	= "/usr/bin/xargs";
	$prg_cut	= "/bin/cut";
	$prg_mkdir	= "/bin/mkdir";
	$prg_ls		= "/bin/ls";
	/***************** END CONFIGURATION *************************/

	/***************** SAMPLE suspend.sh *************************/
	/* #!/bin/bash                                               */
	/* echo "Would have suspended $0"                            */
	/******************** END SAMPLE *****************************/

	/************** INSTALLATION INSTRUCTIONS ********************/
	/* Put this file in /root/payg/check.php                     */
	/* Create a "users" file with the users you want to monitor  */
	/* on the system, one line at a time.  Put a space after each*/
	/* and put a credits amount after the space.  Example:       */
	/*                                                           */
	/* root 100                                                  */
	/* cobi 1000                                                 */
	/* someotheruser 10                                          */
	/*                                                           */
	/* Finally, put this on a crontab (or if you are just        */
	/* playing around with it, run it with php check.php).       */
	/*************************************************************/

	$processes = shell_exec($prg_ps.' aux|'.$prg_awk.' \'{ print $1" "$2" "$3" "$4 }\''); /* Get Process List, parse it into <user> <cpu> <mem> */
	$processes2 = shell_exec($prg_ps.' aux | '.$prg_awk.' \'{ OFS = " "; $1 = "0"; print $0 }\' | '.$prg_cut.' --delimiter=" " --fields=2,11-');
	$sockets = shell_exec($prg_netstat.' -t -p --numeric --wide -e |'.$prg_awk.' \'{ print $9 }\'');
	$users = file_get_contents($usersf); /* Get Users List */
	$users = explode("\n", $users); /* Parse it into an array */
	$counter = 0;
	$total = (count($users) - 1);
	while ($counter <= $total) {
		$user[$counter] = explode(" ", $users[$counter]);
		$tmp = $user[$counter];
		if ($tmp[0]) {
			$money = 0;
			#		echo "Running: ".'echo \''.$processes.'\' |grep '.$tmp[0]."\n";
			$uprocesses = shell_exec($prg_echo.' '.escapeshellarg($processes).' |'.$prg_grep.' '.escapeshellarg('^'.$tmp[0].' '));
			$uprocesses = explode("\n", $uprocesses);
			$counter2 = 0;
			$total2 = (count($uprocesses) - 1);
			
			$log_proc = '';
			while ($counter2 <= $total2) {
				$tmp2 = explode(" ", $uprocesses[$counter2]);
				if ($tmp2[0] != "") {
					$socks = intval(shell_exec($prg_echo.' '.escapeshellarg($sockets).' |'.$prg_grep.' '.escapeshellarg('^'.$tmp2[1]).'|'.$prg_wc.' -l|'.$prg_xargs));
					$cpu = $tmp2[2];
					$mem = $tmp2[3];
					#					echo "User: ".$tmp[0]."\tSockets: ".$socks."\nCPU: ".$cpu."\tMem: ".$mem."\n";
					$moneytmp = 0;
					if ($mem < 1.0) { $moneytmp = $moneytmp + $mem01; }
					elseif (($mem >= 1.0) && ($mem < 2.0)) { $moneytmp = $moneytmp + $mem12; }
					elseif (($mem >= 2.0) && ($mem < 3.0)) { $moneytmp = $moneytmp + $mem23; }
					elseif (($mem >= 3.0) && ($mem < 4.0)) { $moneytmp = $moneytmp + $mem34; }
					elseif ($mem > 4.0) { $moneytmp = $moneytmp + $mem4; }
					if ($cpu < 1.0) { $moneytmp = $moneytmp + $cpu01; }
					elseif (($cpu >= 1.0) && ($cpu < 2.0)) { $moneytmp = $moneytmp + $cpu12; }
					elseif (($cpu >= 2.0) && ($cpu < 3.0)) { $moneytmp = $moneytmp + $cpu23; }
					elseif (($cpu >= 3.0) && ($cpu < 4.0)) { $moneytmp = $moneytmp + $cpu34; }
					elseif ($cpu >= 4.0) { $moneytmp = $moneytmp + $cpu4; }
					if ($socks > $squota) { $moneytmp = $moneytmp + $socket; }
					$money = $money + $moneytmp;
					$procname = shell_exec($prg_echo.' '.escapeshellarg($processes2).' |'.$prg_grep.' '.escapeshellarg('^'.$tmp2[1].' ').' |'.$prg_cut.' --delimiter=" " --fields=2-');
					$log_proc = $log_proc."$".$moneytmp."\t\t".$tmp2[1]."\t".$mem."\t".$cpu."\t".$socks."\t\t".$procname;
				}
				$counter2 = $counter2 + 1;
			}
			$money_proc = $money;
			$diskusage = shell_exec($prg_find.' /var/tmp /tmp ~'.$tmp[0].'/ -user '.$tmp[0].' -type f -print0 | '.$prg_xargs.' -0 '.$prg_ls.' -s | '.$prg_awk.' \'{ test += $1 } END { print test }\'');
			$diskusage = $diskusage / 1024;
			$money_disk = 0;
			if ($diskusage > $dquota) { $money_disk = $money_disk + (($diskusage - $dquota) * $disk); }
			$money = ($money + $money_disk);
			$log = "USER:\t\t".$tmp[0]."\nDATE:\t\t".date('F j, Y G:i')."\n\nProcesses:\t".$total2."\n  Fee\t\t PID\tMem\tCPU\tSockets\t\tProcess Name\n-------\t\t-----\t---\t---\t-------\t\t------------\n".$log_proc."\nProcess Fee:\t$".$money_proc."\n\nDisk Usage:\t".$diskusage." MB\nDisk Fee:\t$".$money_disk."\n\n\nProcesses:\t$".$money_proc."\nDisk Usage:\t$".$money_disk."\nTOTAL:\t\t$".$money;
			echo "User: ".$tmp[0]."\tDisk Usage: ".$diskusage."\n";
			$user[$counter][1] = ($user[$counter][1] - $money);
			$log = $log."\n\n\nBALANCE:\t$".$tmp[1]."\nTOTAL:\t\t$".$money."\nNEW BALANCE:\t$".$user[$counter][1];
			if ($loging == 1) {
				#				echo "writing log file...";
				$null = shell_exec($prg_mkdir.' -p '.$logdir.'/'.$tmp[0].'/');
				$null = shell_exec($prg_echo.' '.escapeshellarg($log).' >'.$logdir.'/'.$tmp[0].'/'.date('Hi.Md.Y').'.log');
				#				echo " Done.\n";
			}
			if (($user[$counter][1] < 0) and ($money > 0) and ($suspend == 1)) { echo shell_exec($suspendscr.' '.$tmp[0]); }
			echo "Money: ".$tmp[1]."\tDeducted: ".$money."\tAfter: ".$user[$counter][1]."\n";
		}
		$counter = $counter + 1;
	}
	#	print_r($user);
        $counter = 0;
        $total = (count($user) - 1);
        while ($counter <= $total) {
		$user[$counter] = implode(" ", $user[$counter]);
		$counter = $counter + 1;
	}
	#	print_r($user);
	$user = implode("\n", $user);
	#echo $user;
	file_put_contents($usersf, $user);
?>