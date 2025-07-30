<?php
namespace MySamples\Controller;

/**
 * See and manage the servers in a cluster.
 *
 * @Route("/server")
 */
class ServerController extends BaseController
{
    /**
     * Get server table from Aws .
     *
     * @param int $clusterId optional
     *
     * @return Response
     *
     * @Route("/{clusterId}")
     * @Route("/")
     * @Route("")
     */
    public function indexAction($clusterId = null)
    {
        try {
            $api = $this->getApi()->getAwsCluster();

            $id = (null === $clusterId) ? null : intval($clusterId);
            $servers = $api->getClusterServers($id);

            return $this->getView($servers);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->get('logger')->err($message);
            return $this->getView([], $message);
        }
    }

    private function getView($servers = array(), $error = '')
    {
        return $this->render("MySample:Server:index.html.php", array(
              'servers' => $servers,
              'error' => $error,
              'user' => $this->getUser()
          ));
    }

    /**
     * Get the health status of a server
     *
     * @param string $ip
     *
     * @return Response array of json encoded base64_encoded image
     *
     *  @Route("/status/{ip}")
     */
    public function statusAction($ip)
    {
        $privateIp = Ethanol::ip()->sanitize($ip);

        if (!$privateIp) {
            return new Response($this->getImage(false));
        }

        $serverConn = 'tcp://'.$privateIp.':80';
        $data = '';
        $errno = 0;
        $errstr = '';
        $ok = true;
        $matches = array();

        try {
            $fp = \stream_socket_client($serverConn, $errno, $errstr, 30, \STREAM_CLIENT_CONNECT);
        } catch (\ErrorException $e) {
            return new Response($this->getImage(false));
        }

        if (!$fp) {
            $ok = false;
        } else {
            $out = "GET /ping HTTP/1.1\r\nHost: localhost\r\n\r\n";
            \fwrite($fp, $out);
             while (!feof($fp)) {
                 $chunk = \fgets($fp, 128);
                 $data .= $chunk;
             }
             \fclose($fp);

             if (preg_match('/HTTP\/1.1 (.+)\r\n/', $data, $matches)) {
                 if (preg_match('/200 OK/', $matches[0])) {
                     $ok = true;
                 } else {
                     $ok = false;
                 }
             } else {
                 $ok = false;
             }
        }

        return new Response($this->getImage($ok));
    }

    /**
     * Get our image based on happy or sad path of the server.
     *
     * @param boolean $ok
     *
     * @return json_encoded array of base64_encoded image.
     */
    private function getImage($ok)
    {
        $dirs = array(__DIR__.'/../Resources/public/images/', __DIR__.'/../../Resources/public/images/');
        $location = new FileLocator($dirs);

        if ($ok) {
            $filename = $location->locate('server_thumb_green.png');
        } else {
            $filename = $location->locate('server_thumb_red.png');
        }

        $contents = \file_get_contents($filename);

        return \json_encode(array('img' => 'data:image/png;base64,' . \base64_encode($contents)));
    }
}
