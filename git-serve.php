   <?php
      require_once 'includes/auth.php';
      require_once 'includes/repository.php';

      $repo = $_GET['repo'] ?? null;
      $username = $_GET['user'] ?? $_SESSION['username'] ?? null;

      if (!$repo && !$username) {
          header('HTTP/1.1 400 Bad Request');
          echo "Repository not specified.";
          exit;
      }
      echo $repo;
      //take the last char off username
      $username = substr($username, 0, -1);
      echo $username;


      // Get repository info
      $repoInfo = getRepositoryInfo($username, $repo);
      if (!$repoInfo || !canAccessRepository($repoInfo)) {
          header('HTTP/1.1 403 Forbidden');
          exit;

      }

      $repoPath = getRepoPath($username, $repo);

      // Determine the Git command to run based on the request
      $service = $_GET['service'] ?? null;
      if ($service === 'git-upload-pack') {
          $gitCommand = "git-upload-pack";
      } elseif ($service === 'git-receive-pack') {
          $gitCommand = "git-receive-pack";
      } else {
          header('HTTP/1.1 400 Bad Request');
          echo "Invalid service.";
          exit;
      }

      // Set the appropriate content type
      header('Content-Type: application/x-' . $service . '-advertisement');

      // Execute the Git command and stream the output
      $descriptorspec = [
          0 => ["pipe", "r"], // stdin
          1 => ["pipe", "w"], // stdout
          2 => ["pipe", "w"]  // stderr
      ];

      $process = proc_open("git $gitCommand --stateless-rpc --advertise-refs $repoPath", $descriptorspec, $pipes);

      if (is_resource($process)) {
          fclose($pipes[0]); // Close stdin

          // Stream stdout to the client
          while ($line = fgets($pipes[1])) {
              echo $line;
              flush();
          }
          fclose($pipes[1]);

          // Capture and log stderr
          $stderr = stream_get_contents($pipes[2]);
          fclose($pipes[2]);

          proc_close($process);

          if ($stderr) {
              error_log("Git error: $stderr");
          }
      } else {
          header('HTTP/1.1 500 Internal Server Error');
          echo "Failed to execute Git command.";
      }
      <?php
      require_once 'includes/auth.php';
      require_once 'includes/repository.php';

      $repo = $_GET['repo'] ?? null;
      $username = $_GET['user'] ?? $_SESSION['username'] ?? null;

      if (!$repo) {
          header('HTTP/1.1 400 Bad Request');
          echo "Repository not specified.";
          exit;
      }

      // Get repository info
      $repoInfo = getRepositoryInfo($username, $repo);

      if (!$repoInfo || !canAccessRepository($repoInfo)) {
          header('HTTP/1.1 403 Forbidden');
          echo "Access denied.";
          exit;
      }

      $repoPath = getRepoPath($username, $repo);

      // Handle Git service requests
      if (isset($_GET['service'])) {
          $service = $_GET['service'];
          if ($service === 'git-upload-pack' || $service === 'git-receive-pack') {
              header('Content-Type: application/x-' . $service . '-advertisement');
              echo "# service=$service\n";
              echo "0000";

              $descriptorspec = [
                  0 => ["pipe", "r"], // stdin
                  1 => ["pipe", "w"], // stdout
                  2 => ["pipe", "w"]  // stderr
              ];

              $process = proc_open("git $service --stateless-rpc --advertise-refs $repoPath", $descriptorspec, $pipes);

              if (is_resource($process)) {
                  fclose($pipes[0]); // Close stdin

                  // Stream stdout to the client
                  while ($line = fgets($pipes[1])) {
                      echo $line;
                      flush();
                  }
                  fclose($pipes[1]);

                  // Capture and log stderr
                  $stderr = stream_get_contents($pipes[2]);
                  fclose($pipes[2]);

                  proc_close($process);

                  if ($stderr) {
                      error_log("Git error: $stderr");
                  }
              } else {
                  header('HTTP/1.1 500 Internal Server Error');
                  echo "Failed to execute Git command.";
              }
              exit;
          }
      }

      // Handle info/refs requests
      if (preg_match('/\/info\/refs$/', $_SERVER['REQUEST_URI'])) {
          $service = $_GET['service'] ?? null;
          if ($service === 'git-upload-pack' || $service === 'git-receive-pack') {
              header('Content-Type: application/x-' . $service . '-advertisement');
              echo "# service=$service\n";
              echo "0000";

              $descriptorspec = [
                  0 => ["pipe", "r"], // stdin
                  1 => ["pipe", "w"], // stdout
                  2 => ["pipe", "w"]  // stderr
              ];

              $process = proc_open("git $service --stateless-rpc --advertise-refs $repoPath", $descriptorspec, $pipes);

              if (is_resource($process)) {
                  fclose($pipes[0]); // Close stdin

                  // Stream stdout to the client
                  while ($line = fgets($pipes[1])) {
                      echo $line;
                      flush();
                  }
                  fclose($pipes[1]);

                  // Capture and log stderr
                  $stderr = stream_get_contents($pipes[2]);
                  fclose($pipes[2]);

                  proc_close($process);

                  if ($stderr) {
                      error_log("Git error: $stderr");
                  }
              } else {
                  header('HTTP/1.1 500 Internal Server Error');
                  echo "Failed to execute Git command.";
              }
              exit;
          }
      }

      // Handle other Git requests
      $input = file_get_contents('php://input');
      $descriptorspec = [
          0 => ["pipe", "r"], // stdin
          1 => ["pipe", "w"], // stdout
          2 => ["pipe", "w"]  // stderr
      ];

      $process = proc_open("git $service --stateless-rpc $repoPath", $descriptorspec, $pipes);

      if (is_resource($process)) {
          fwrite($pipes[0], $input);
          fclose($pipes[0]); // Close stdin

          // Stream stdout to the client
          while ($line = fgets($pipes[1])) {
              echo $line;
              flush();
          }
          fclose($pipes[1]);

          // Capture and log stderr
          $stderr = stream_get_contents($pipes[2]);
          fclose($pipes[2]);

          proc_close($process);

          if ($stderr) {
              error_log("Git error: $stderr");
          }
      } else {
          header('HTTP/1.1 500 Internal Server Error');
          echo "Failed to execute Git command.";
      }
