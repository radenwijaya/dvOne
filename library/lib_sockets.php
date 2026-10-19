<?php
	trait unix_socket
	/*
	 * This trait is for IPC using UNIX Socket, do not use with TCP / UDP
	 * 
	 * Limitation: max write is 1 TB
	 */
	{
		var $sockets;
		var $socket_fibers;
		
		function prepare_server_socket($address)
		{
			$fiber=new Fiber(
				function ($address, $fiber)
				{					
					$sock_address=sys_get_temp_dir().'/'.$address;
					
					$socket=socket_create(AF_UNIX, SOCK_STREAM, 0);

					unlink($sock_address); // delete any file that might be available
					socket_bind($socket, $sock_address);
					
					socket_listen($socket);

					$client_socket=socket_accept($socket);
					socket_set_nonblock($socket);					
					$this->sockets['server']=$socket;
					$this->sockets['client']=$client_socket;
					
//					socket_connect($client_socket, $sock_address);
					$this->receiver_socket_run($fiber, $client_socket);
					
					
					return $client_socket;
				}
			);
			
			$fiber->start($address, $fiber);
			$this->socket_fibers['server']=$fiber;			
			
			return $fiber;
		}
		
		function receiver_socket_run($fiber, $socket)		
		{
			$fiber->suspend();
			$exit=false; 
			while (!$exit)
			{
				$len=socket_read($socket, 12);
				
				$data=socket_read($socket, (int) $len);	
				
				$msg=json_decode($data, true);
//				var_dump($len, $msg);
				switch ($msg['type'])
				{
					case 'msg':
						$result=$msg['msg'];						
						break;
					case 'cmd':
						switch ($msg['cmd'])
						{
							case 'die':								
								$exit=true;
							break;
						}
						break;
				}
				
				if (!$exit)
				{
					$fiber->suspend($result);
				}
			}					
		}
		
		function prepare_client_socket($address, $attempt=5)
		{			
			$socket=socket_create(AF_UNIX, SOCK_STREAM, 0);
			socket_clear_error($socket);
			$sock_address=sys_get_temp_dir().'/'.$address;
			
			$fiber=new Fiber(
				function ($socket, $address, $attempt, $fiber)
				{
					$i=1;
					
					while ((!$link=socket_connect($socket, $address)) && ($i<$attempt))
					{
						sleep(1);
				
						$i++;
					}
					
					if ($link)
					{												
						$this->receiver_socket_run($fiber, $socket);
					}
				}
			);				
			
			$fiber->start($socket, $sock_address, $attempt, $fiber);
			$this->sockets['client']=$socket;
			
			return $fiber;
		}
		
		function socket_write($sock, $message)
		/*
		 * Always first send len as 16 bytes
		 */
		{
			$socket=$this->sockets[$sock];
			if (socket_last_error($socket)==0)
			{			
				$data=['type' => 'msg', 'msg' => $message];
				$to_send=json_encode($data);
				$len=strlen($to_send);
								
				socket_write($socket, str_pad($len, 12, "0", STR_PAD_LEFT), 16);
				socket_write($socket, $to_send, $len);								
			}						
		}
		
		function socket_close(...$socks)
		{
			foreach ($socks as $sock)
				socket_close($this->sockets[$sock]);
		}
		
/*		function socket_terminate($sock)
		{
			$socket=$this->sockets[$sock];
			
			$message=json_encode(['type' => 'cmd', 'cmd' => 'die']);
			
			$len=strlen($message);
			//Always 12 bytes character
			socket_write($socket, str_pad($len, 12, "0", STR_PAD_LEFT), 12);
			socket_write($socket, $message, $len);
			
			socket_close($this->sockets[$sock]);
		}*/
	}
?>