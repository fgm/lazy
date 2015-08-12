<?php
/**
 * @file
 * Contains Runner.
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy\Runner;

use OSInet\Lazy\Controller\BuilderInterface;

/**
 * Class Forking is a two-process runner.
 *
 * As such, it allows never-returning pages, like those sending data on their
 * own, or invoking drupal_goto(), or any other "end-of-process" behaviors.
 *
 * @package OSInet\Lazy\Runner
 *
 * @FIXME completely broken work in progress.
 */
class Forking extends Base {
  /**
   * The timeout for stream_select() calls.
   */
  const SELECT_TIMEOUT = 5;

  /**
   * Log a socket error.
   *
   * @param string $channel
   *   The name of the logging channel.
   * @param string $message
   *   A logger message template.
   * @param int $severity
   *   The event severity, a WATCHDOG_* constant.
   */
  protected function socketLog($channel, $message, $severity) {
    $errno = socket_last_error();
    $err_str = socket_strerror($errno);
    watchdog("lazy/$channel", $message, [
      '@errno' => $errno,
      '@error' => $err_str,
    ], $severity);
  }

  /**
   * Log a process control error.
   *
   * @param string $channel
   *   The name of the logging channel.
   * @param string $message
   *   A logger message template.
   * @param int $severity
   *   The event severity, a WATCHDOG_* constant.
   */
  protected function pcntlLog($channel, $message, $severity) {
    $errno = pcntl_get_last_error();
    $err_str = pcntl_strerror($errno);
    watchdog("lazy/$channel", $message, [
      '@errno' => $errno,
      '@error' => $err_str,
    ], $severity);
  }

  /**
   * {@inheritdoc}
   */
  public function run(callable $builder, array $args = []) {
    list($parent, $child) = stream_socket_pair(AF_UNIX, SOCK_STREAM, IPPROTO_IP);

    $pid = pcntl_fork();

    // In parent, fork failed.
    if ($pid < 0) {
      $this->pcntlLog('parent', 'Fork error @errno : @error', WATCHDOG_ERROR);
    }

    // In parent, fork succeeded.
    elseif ($pid > 0) {
      fclose($parent);

      /* CAVEAT: this code::
      - Assumes fails only happen during the execution of the child controller.
      - Does not handle failures during static::run() itself.
      - Assumes that no error will occur during socket transmission itself.
       */

      // First read the message length in fixed format.
      $read = [$child];
      $written = [];
      $except = [];
      watchdog('lazy/parent', __LINE__, [], WATCHDOG_DEBUG);
      $sts = stream_select($read, $written, $except, static::SELECT_TIMEOUT);
      watchdog('lazy/parent', __LINE__, [], WATCHDOG_DEBUG);
      switch ($sts) {
        case FALSE:
          $this->socketLog('parent', 'Error @errno reading length of child return', WATCHDOG_WARNING);
          $ret = NULL;
          break;

        case 0:
          watchdog('lazy/parent', 'Stream select for length returned on timeout, without data.', [], WATCHDOG_NOTICE);
          $ret = NULL;
          break;

        case 1:
          $sts = fread($child, 8);
          if ($sts === FALSE) {
            $this->socketLog('parent', 'Error @errno reading length of child return: @error', WATCHDOG_WARNING);
            $ret = NULL;
          }
          else {
            sscanf($sts, '%08x', $length);
            $read = [$child];

            // Then read the actual message now that its length is known.
            $sts = stream_select($read, $written, $except, static::SELECT_TIMEOUT);
            switch ($sts) {
              case FALSE:
                $this->socketLog('parent', 'Error @errno reading child return', WATCHDOG_WARNING);
                $ret = NULL;
                break;

              case 0:
                watchdog('lazy/parent', 'Stream select for data returned on timeout, without data.', [], WATCHDOG_NOTICE);
                $ret = NULL;
                break;

              case 1:
                $serialized = fread($child, $length);
                if ($serialized === FALSE) {
                  $this->socketLog('parent', 'Error @errno reading child return: @error', WATCHDOG_WARNING);
                  $ret = NULL;
                }
                else {
                  $build = unserialize($serialized);
                  $ret = $build;
                }
                break;

              default:
                watchdog('lazy/parent', 'Unexpected stream select for data result: @count', ['@count' => $sts], WATCHDOG_ERROR);
                $ret = NULL;
            }
          }
          break;

        default:
          watchdog('lazy/parent', 'Unexpected stream select for length result: @count', ['@count' => $sts], WATCHDOG_ERROR);
          $ret = NULL;
      }

      fclose($child);
      watchdog('lazy/parent', __LINE__, [], WATCHDOG_DEBUG);

      $res = pcntl_waitpid($pid, $status, WNOHANG | WUNTRACED);
      assert("$res == $pid");
      watchdog('lazy/parent', __LINE__, [], WATCHDOG_DEBUG);

      // Child exited.
      if (pcntl_wifexited($status)) {
        $exit_status = pcntl_wexitstatus($status);
        watchdog('lazy/parent', 'Builder child exited: @status', ['@status' => $exit_status], WATCHDOG_NOTICE);
      }
      // Child was killed by a signal. This is not clean.
      elseif (pcntl_wifsignaled($status)) {
        $signal = pcntl_wtermsig($status);
        watchdog('lazy/parent', 'Builder child was killed by signal @signal.', ['@signal' => $signal], WATCHDOG_WARNING);
      }
      // Child was stopped. This is not supported.
      elseif (pcntl_wifstopped($status)) {
        $signal = pcntl_wstopsig($status);
        watchdog('lazy/parent', 'Builder child was stopped by signal @signal.', ['@signal' => $signal], WATCHDOG_ERROR);
      }
      // Child finished without exiting.
      else {
        watchdog('lazy/parent', 'Builder finished without exiting.', [], WATCHDOG_DEBUG);
      }

      watchdog('lazy/parent', __LINE__, [], WATCHDOG_DEBUG);
      $this->result = $ret;
    }

    // Child process.
    else {
      fclose($child);
      sleep(6);
      watchdog('lazy/child', __LINE__, [], WATCHDOG_DEBUG);
      $build = call_user_func_array($this->builder, $this->args);
      watchdog('lazy/child', __LINE__, [], WATCHDOG_DEBUG);
      $serialized = serialize($build);
      watchdog('lazy/child', __LINE__, [], WATCHDOG_DEBUG);

      // Send message length in a fixed-length format.
      $length = sprintf('%08x', strlen($serialized));
      fwrite($parent, $length, 8);
      watchdog('lazy/child', __LINE__, [], WATCHDOG_DEBUG);
      fflush($parent);
      watchdog('lazy/child', __LINE__, [], WATCHDOG_DEBUG);

      // Now send the actual data.
      fwrite($parent, $serialized);
      watchdog('lazy/child', __LINE__, [], WATCHDOG_DEBUG);
      fflush($parent);
      watchdog('lazy/child', __LINE__, [], WATCHDOG_DEBUG);

      fclose($parent);
      watchdog('lazy/child', __LINE__, [], WATCHDOG_DEBUG);
      exit(0);
    }
  }

}
