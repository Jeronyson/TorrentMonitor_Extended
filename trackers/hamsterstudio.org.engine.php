<?php
class hamsterstudioorg
{
    protected static $sess_cookie;
    protected static $exucution;
    protected static $warning;
    
    protected static $page;    
    protected static $log_page;
    protected static $xml_page;

    //проверяем cookie
    public static function checkCookie($sess_cookie)
    {
        $result = Sys::getUrlContent(
            array(
                'type'           => 'POST',
                'returntransfer' => 1,
                'url'            => 'http://hamsterstudio.org',
                'cookie'         => hamsterstudioorg::$sess_cookie,
                'sendHeader'     => array('Host' => 'hamsterstudio.org', 'Content-length' => strlen(hamsterstudioorg::$sess_cookie)),
                'convert'        => array('windows-1251', 'utf-8//IGNORE'),
            )
        );

        if (preg_match('/<a href=\"logout\.php\">Выход<\/a>/U', $result))
            return TRUE;
        else
            return FALSE;          
    }
    
    //функция проверки введёного URL`а
    public static function checkRule($data)
    {
        if (preg_match('/^[\.\+\s\'\`\:\;\-a-zA-Z0-9]+$/', $data))
            return TRUE;
        else
            return FALSE;
    }
    
    //функция преобразования даты в строку
    private static function dateNumToString($data)
    {
        $data = substr($data, 0, -3);
        $data = preg_split('/\s/', $data);
        $time = $data[1];
        $data = $data[0];
        $data = preg_split('/\-/', $data);

        $month = Sys::dateNumToString($data[1]);
        $date = $data[2].' '.$month.' '.$data[0].' '.$time;
        
        return $date;
    }

    //функция анализа эпизода
    private static function analysisEpisode($item)
    {
        preg_match('/s\d{2}\.?e\d{2}/i', $item->link, $matches);
        if (isset($matches[0]))
        {
            $episode = $matches[0];
            preg_match('/Добавлен: (.*)\n/', $item->description, $array);
            $date = $array[1];
            return array('episode'=>$episode, 'date'=>$date, 'link'=>(string)$item->link);
        }
    }
    
    //функция анализа xml ленты
    private static function analysis($name, $hd, $item)
    {
        if (preg_match('/'.$name.'/i', (string)$item->title))
        {
            if ($hd == 1)
            {
                if (preg_match_all('/720/', $item->title, $matches))
                    return hamsterstudioorg::analysisEpisode($item);
            }
            elseif ($hd == 2)
            {
                if (preg_match_all('/1080p/', $item->title, $matches))
                    return hamsterstudioorg::analysisEpisode($item);
            }
            else
            {
                if (preg_match_all('/^(?!(.*720|.*1080))/', $item->link, $matches))
                    return hamsterstudioorg::analysisEpisode($item);
            }
        }
    }

    //функция получения кук
    public static function getCookie($tracker)
    {
        //проверяем заполнены ли учётные данные
        if (Database::checkTrackersCredentialsExist($tracker))
        {
            //получаем учётные данные
            $credentials = Database::getCredentials($tracker);
            $login = iconv('utf-8', 'windows-1251', $credentials['login']);
            $password = $credentials['password'];
            
            $page = Sys::getUrlContent(
                array(
                    'type'           => 'POST',
                    'header'         => 1,
                    'returntransfer' => 1,
                    'url'            => 'http://hamsterstudio.org/takelogin.php',
                    'postfields'     => 'username='.$login.'&password='.$password,
                    'convert'        => array('windows-1251', 'utf-8//IGNORE'),
                )
            );

            if ( ! empty($page))
            {
                //проверяем подходят ли учётные данные
                if (preg_match_all('/Set-Cookie: (\w*)=(\S*)/', $page, $array))
                {
                    if (count($array[0]) == 3)
                    {
                        hamsterstudioorg::$sess_cookie = $array[1][1].'='.$array[2][1].' '.$array[1][2].'='.$array[2][2];
                        Database::setCookie($tracker, hamsterstudioorg::$sess_cookie);
                        //запускам процесс выполнения, т.к. не может работать без кук
                        hamsterstudioorg::$exucution = TRUE;
                    }
                    //иначе не верный логин или пароль
                    else
                    {
                        //устанавливаем варнинг
                        if (hamsterstudioorg::$warning == NULL)
                        {
                            hamsterstudioorg::$warning = TRUE;
                            Errors::setWarnings($tracker, 'credential_wrong');
                        }
                        //останавливаем выполнение цепочки
                        hamsterstudioorg::$exucution = FALSE;
                    }
                }
                //если не удалось получить никаких данных со страницы, значит трекер не доступен
                else
                {
                    //устанавливаем варнинг
                    if (hamsterstudioorg::$warning == NULL)
                    {
                        hamsterstudioorg::$warning = TRUE;
                        Errors::setWarnings($tracker, 'not_available');
                    }
                    //останавливаем выполнение цепочки
                    hamsterstudioorg::$exucution = FALSE;
                }
            }
            else
            {
                //устанавливаем варнинг
                if (hamsterstudioorg::$warning == NULL)
                {
                    hamsterstudioorg::$warning = TRUE;
                    Errors::setWarnings($tracker, 'not_available');
                }
                //останавливаем выполнение цепочки
                hamsterstudioorg::$exucution = FALSE;
            }

        }
        else
        {
            //устанавливаем варнинг
            if (hamsterstudioorg::$warning == NULL)
            {
                hamsterstudioorg::$warning = TRUE;
                Errors::setWarnings($tracker, 'credential_miss');
            }
            //останавливаем выполнение цепочки
            hamsterstudioorg::$exucution = FALSE;                        
        }    
    }

    //основная функция
    public static function main($torrentInfo)
    {
        extract($torrentInfo);
        
        //проверяем небыло ли до этого уже ошибок
        if (empty(hamsterstudioorg::$exucution) || (hamsterstudioorg::$exucution))
        {
            //проверяем получена ли уже кука
            if (empty(hamsterstudioorg::$sess_cookie))
            {
                $cookie = Database::getCookie($tracker);
                if (hamsterstudioorg::checkCookie($cookie))
                {
                    hamsterstudioorg::$sess_cookie = $cookie;
                    //запускам процесс выполнения
                    hamsterstudioorg::$exucution = TRUE;
                }            
                else
                    hamsterstudioorg::getCookie($tracker);
            }

            //проверяем получена ли уже RSS лента
            if ( ! hamsterstudioorg::$log_page)
            {
                if (hamsterstudioorg::$exucution)
                {
                    //получаем страницу
                    $page = Sys::getUrlContent(
                        array(
                            'type'           => 'GET',
                            'returntransfer' => 1,
                            'url'            => 'http://hamsterstudio.org/rss.php?feed=dl',
                            'cookie'         => hamsterstudioorg::$sess_cookie,
                            'sendHeader'     => array('Host' => 'hamsterstudio.org', 'Content-length' => strlen(hamsterstudioorg::$sess_cookie)),
                            'convert'        => array('windows-1251', 'utf-8//IGNORE'),
                        )
                    );

                    $page = str_replace('<?xml version="1.0" encoding="windows-1251" ?>','<?xml version="1.0" encoding="utf-8"?>', $page);
                    if ( ! empty($page))
                    {
                        $xml_page = str_replace(array("&amp;", "&"), array("&", "&amp;"), $page);
                        //читаем xml
                        hamsterstudioorg::$xml_page = @simplexml_load_string($xml_page);
                        //если XML пришёл с ошибками - останавливаем выполнение, иначе - ставим флажок, что получаем страницу
                        if ( ! hamsterstudioorg::$xml_page)
                        {
                            //устанавливаем варнинг
                            if (hamsterstudioorg::$warning == NULL)
                            {
                                hamsterstudioorg::$warning = TRUE;
                                Errors::setWarnings($tracker, 'rss_parse_false');
                            }
                            //останавливаем выполнение цепочки
                            hamsterstudioorg::$exucution = FALSE;
                        }
                        else
                            hamsterstudioorg::$log_page = TRUE;
                    }
                    else
                    {
                        //устанавливаем варнинг
                        if (hamsterstudioorg::$warning == NULL)
                        {
                            hamsterstudioorg::$warning = TRUE;
                            Errors::setWarnings($tracker, 'not_available');
                        }
                        //останавливаем выполнение цепочки
                        hamsterstudioorg::$exucution = FALSE;
                    }
                }
            }
        }
        
        //если выполнение цепочки не остановлено
        if (hamsterstudioorg::$exucution)
        {
            if ( ! empty(hamsterstudioorg::$xml_page))
            {
                //сбрасываем варнинг
                Database::clearWarnings($tracker);
                $nodes = array();
                foreach (hamsterstudioorg::$xml_page->channel->item AS $item)
                {
                    array_unshift($nodes, $item);
                }
                
                foreach ($nodes as $item)
                {
                    $serial = hamsterstudioorg::analysis($name, $hd, $item);
                    if ( ! empty($serial))
                    {
                        $episode = substr($serial['episode'], 4, 2);
                        $season = substr($serial['episode'], 1, 2);
                        $date_str = hamsterstudioorg::dateNumToString($serial['date']);

                        if ( ! empty($ep))
                        {
                            if ($season == substr($ep, 1, 2) && $episode > substr($ep, 4, 2))
                                $download = TRUE;
                            elseif ($season > substr($ep, 1, 2) && $episode < substr($ep, 4, 2))
                                $download = TRUE;
                            else
                                $download = FALSE;
                        }
                        elseif ($ep == NULL)
                            $download = TRUE;
                        else
                            $download = FALSE;

                        if ($download)
                        {
                            $amp = ($hd) ? 'HD' : NULL;
                            //сохраняем торрент в файл
                            $torrent = Sys::getUrlContent(
                                array(
                                    'type'           => 'GET',
                                    'returntransfer' => 1,
                                    'url'            => $serial['link'],
                                    'cookie'         => hamsterstudioorg::$sess_cookie,
                                    'sendHeader'     => array('Host' => 'hamsterstudio.org', 'Content-length' => strlen(hamsterstudioorg::$sess_cookie)),
                                )
                            );
                            
                            if (Sys::checkTorrentFile($torrent))
                            {                            
                                $file = str_replace(' ', '.', $name).'.S'.$season.'E'.$episode.'.'.$amp;
                                $episode = (substr($episode, 0, 1) == 0) ? substr($episode, 1, 1) : $episode;
                                $season = (substr($season, 0, 1) == 0) ? substr($season, 1, 1) : $season;
                                $message = $name.' '.$amp.' обновлён до '.$episode.' серии, '.$season.' сезона.';
                                $status = Sys::saveTorrent($tracker, $file, $torrent, $id, $hash, $message, $date_str);

                                //обновляем время регистрации торрента в базе
                                Database::setNewDate($id, $serial['date']);
                                //обновляем сведения о последнем эпизоде
                                Database::setNewEpisode($id, $serial['episode']);
                            }
                            else
                                Errors::setWarnings($tracker, 'save_file_fail');                                                    
                        }
                    }
                }
            }
        }
    }
    
    // функция возвращает тип раздач, которые обрабатывает треккер
    public static function getTrackerType() {
        return 'series';
    }
}
?>