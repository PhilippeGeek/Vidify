<?php

namespace Vidify\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{

    private static function thumbmail_vimeo($id){
        return "https://i.vimeocdn.com/video/513572288_250.jpg";
    }

    private static function get_vimeo($id)
    {
        $video = json_decode(file_get_contents("https://player.vimeo.com/video/$id/config"));
        return array(
            'url'=>urlencode($video->request->files->h264->hd->url),
            'thumbnail'=>$video->video->thumbs->base.'_250.jpg',
            'title'=>$video->video->title);
    }

    private static function get_youtube($id, $type = 'video/mp4'){
        parse_str(file_get_contents('http://www.youtube.com/get_video_info?video_id='.$id),$info); //get video info
        $streams = explode(',',$info['url_encoded_fmt_stream_map']); //split the stream map into streams

        $url = null;

        foreach($streams as $stream){
            parse_str($stream,$real_stream); //parse the splitted stream
            $stype = $real_stream['type']; //the MIME type of the stream
            if(strpos($real_stream['type'],';') !== false){ //if a semicolon exists, that means the MIME type has a codec in it
                $tmp = explode(';',$real_stream['type']); //get rid of the codec
                $stype = $tmp[0];
                unset($tmp);
            }
            if($stype == $type && ($real_stream['quality'] == 'large' || $real_stream['quality'] == 'medium' || $real_stream['quality'] == 'small')){ //check whether the format is the desired format
                $url = $real_stream['url'];
                break;
            }
        }

        if($url === null) {
            // Return at least one url
            $stream = $streams[0];
            parse_str($stream, $real_stream);
            $url = $real_stream['url'];
        }

        return array(
            'url' => urlencode($url),
            'thumbnail'=> $info['iurlhq'],
            'title' => $info['title']
        );
    }

    /**
     * @Route("/", name="blog_home")
     * @Template()
     */
    public function indexAction()
    {
        return array();
    }

    /**
     * @Route("/worker", name="worker")
     */
    public function workerAction(){
        @set_time_limit(0); //disable time limit to make sure the whole video is downloaded

        $url = urldecode($this->get('request')->request->get('url'));

        $file = $this->get('kernel')->getRootDir().'/../web/'.md5($url).'.mp4';
        $handle = fopen($file, 'w');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $handle); // write curl response to file
        curl_exec($ch);
        curl_close($ch);
        fclose($handle);
        return new Response(md5($url).'.mp4',200);
    }

    /**
     * @Route("/download")
     * @Template()
     */
    public function downloadAction()
    {
        $vidUrl = $this->get('request')->request->get('url');
        $url = parse_url($vidUrl);
        if(array_key_exists('host',$url)) {
            $host = str_replace("www.","",$url['host']);
            if ($host == 'youtube.com') {
                parse_str($url["query"], $query);
                return DefaultController::get_youtube($query['v']);
            } else if($host == 'vimeo.com'){
                $id = explode('/',$url['path']);
                $id = $id[sizeof($id)-1];
                return DefaultController::get_vimeo($id);
            } else {
                return $this->redirectToRoute('blog_home');
            }
        } else {
            return $this->redirectToRoute('blog_home');
        }
    }
}
