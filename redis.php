<?php

/**
 * Redis version compatibility: 2.4 (also 2.2 and lower)
 *
 * You can send custom command using send_command() method.
 *
 *
 * This class based off of https://github.com/jamm/Memory/blob/master/RedisServer.php
 *
 * For the purposes of fitting in with other classes in crossbar, I converted it to static and added teh concept of multiple connections 
 */
class redis 
{
    private static $repeat_reconnected = false;
    static private $config = array();
    static private $connections = array();
    static private $errors = array();

    const Position_BEFORE = 'BEFORE';
    const Position_AFTER  = 'AFTER';
    const WITHSCORES      = 'WITHSCORES';
    const Aggregate_SUM   = 'SUM';
    const Aggregate_MIN   = 'MIN';
    const Aggregate_MAX   = 'MAX';

    public static function config($alias, $host, $port = 6379)
    {
        self::$config[$alias] = array(
                'host'        => $host,
                'port'        => $port,
                );
    }

    // Verify that the alias has been set up properly
    public static function connect($alias)
    {
        if (empty(self::$connections[$alias]))
        {
            $socket = fsockopen(self::$config[$alias]['host'], self::$config[$alias]['port'], $errno, $errstr);
            if (!$socket)
            {
                self::reportError('Connection error: '.$errno.':'.$errstr);
                return false;
            }   
            else
            {
                self::$connections[$alias] = $socket;
            }
        }
        return self::$connections[$alias];
    }

    public static function reconnect($alias)
    {
        self::$connections[$alias] = NULL;
        return self::connect($alias);
    }

    protected function reportError($msg)
    {
        trigger_error($msg, E_USER_WARNING);
    }

    /**
     * Execute send_command and return the result
     * Each entity of the send_command should be passed as argument
     * Example:
     *  send_command('set','key','example value');
     * or:
     *  send_command('multi');
     *  send_command('set','a', serialize($arr));
     *  send_command('set','b', 1);
     *  send_command('execute');
     * @return array|bool|int|null|string
     */
    public static function send_command($alias)
    {
        return self::_send($alias, func_get_args());
    }

    protected static function _send($alias, $args)
    {
        $connection = self::connect($alias);
        if(!$connection)
        {
            return false;
        }

        $command = '*'.count($args)."\r\n";
        foreach ($args as $arg) $command .= "$".strlen($arg)."\r\n".$arg."\r\n";

        $w = fwrite($connection, $command);
        if (!$w)
        {
            //if connection was lost
            $connection = self::reconnect($alias);
            if (!fwrite($connection, $command))
            {
                self::reportError('command was not sent');
                return false;
            }
        }
        $answer = self::_read_reply($alias);
        if ($answer===false && self::$repeat_reconnected)
        {
            if (fwrite($connection, $command))
            {
                $answer = self::_read_reply();
            }
            self::$repeat_reconnected = false;
        }
        return $answer;
    }

    protected static function _read_reply($alias)
    {
        $connection = self::connect($alias);
        $server_reply = fgets($connection);
        if ($server_reply===false)
        {
            $connection = self::reconnect($alias);
            if(!$connection)
            {
                return false;
            }
            else
            {
                $server_reply = fgets($connection);
                if (empty($server_reply))
                {
                    self::$repeat_reconnected = true;
                    return false;
                }
            }
        }
        $reply    = trim($server_reply);
        $response = null;

        /**
         * Thanks to Justin Poliey for original code of parsing the answer
         * https://github.com/jdp
         * Error was fixed there: https://github.com/jamm/redisent
         */
        switch ($reply[0])
        {
            /* Error reply */
            case '-':
                self::reportError('error: '.$reply);
                return false;
                /* Inline reply */
            case '+':
                return substr($reply, 1);
                /* Bulk reply */
            case '$':
                if ($reply=='$-1') return null;
                $response = null;
                $read     = 0;
                $size     = intval(substr($reply, 1));
                if ($size > 0)
                {
                    do
                    {
                        $block_size = min($size-$read, 4096);
                        if ($block_size < 1) break;
                        $data = fread($connection, $block_size);
                        if ($data===false)
                        {
                            self::reportError('error when reading answer');
                            return false;
                        }
                        $response .= $data;
                        $read += $block_size;
                    } while ($read < $size);
                }
                fread($connection, 2); /* discard crlf */
                break;
                /* Multi-bulk reply */
            case '*':
                $count = substr($reply, 1);
                if ($count=='-1') return null;
                $response = array();
                for ($i = 0; $i < $count; $i++)
                {
                    $response[] = self::_read_reply();
                }
                break;
                /* Integer reply */
            case ':':
                return intval(substr($reply, 1));
                break;
            default:
                self::reportError('Non-protocol answer: '.print_r($server_reply, 1));
                return false;
        }

        return $response;
    }

    public static function get($alias, $key)
    {
        return self::_send($alias, array('get', $key));
    }

    public static function set($alias, $key, $value)
    {
        return self::_send($alias, array('set', $key, $value));
    }

    public static function setex($alias, $key, $seconds, $value)
    {
        return self::_send($alias, array('setex', $key, $seconds, $value));
    }

    public static function keys($alias, $pattern)
    {
        return self::_send($alias, array('keys', $pattern));
    }

    public static function multi($alias)
    {
        return self::_send($alias, array('multi'));
    }

    public static function sadd($alias, $set, $value)
    {
        if (!is_array($value)) $value = func_get_args();
        else array_unshift($value, $set);
        return self::_send($alias, array('sadd', $value));
    }

    public static function smembers($alias, $set)
    {
        return self::_send($alias, array('smembers', $set));
    }

    public static function hset($alias, $key, $field, $value)
    {
        return self::_send($alias, array('hset', $key, $field, $value));
    }

    public static function hgetall($alias, $key)
    {
        $arr = self::_send($alias, array('hgetall', $key));
        $c   = count($arr);
        $r   = array();
        for ($i = 0; $i < $c; $i += 2)
        {
            $r[$arr[$i]] = $arr[$i+1];
        }
        return $r;
    }

    public static function flushdb($alias)
    {
        return self::_send($alias, array('flushdb'));
    }

    public static function info($alias)
    {
        return self::_send($alias, array('info'));
    }

    public static function setnx($alias, $key, $value)
    {
        return self::_send($alias, array('setnx', $key, $value));
    }

    public static function watch($alias)
    {
        $args = func_get_args();
        array_unshift($args, 'watch');
        return self::_send($alias, $args);
    }

    public static function exec($alias)
    {
        return self::_send($alias, array('exec'));
    }

    public static function discard($alias)
    {
        return self::_send($alias, array('discard'));
    }

    public static function sismember($alias, $set, $value)
    {
        return self::_send($alias, array('sismember', $set, $value));
    }

    public static function srem($alias, $set, $value)
    {
        if (!is_array($value)) $value = func_get_args();
        else array_unshift($value, $set);
        return self::_send($alias,array('srem', $value));
    }

    public static function expire($alias, $key, $seconds)
    {
        return self::_send($alias, array('expire', $key, $seconds));
    }

    public static function ttl($alias, $key)
    {
        return self::_send($alias, array('ttl', $key));
    }

    public static function del($alias, $key)
    {
        return self::_send($alias, array('del', $key));
    }

    public static function incrby($alias, $key, $increment)
    {
        return self::_send($alias, array('incrby', $key, $increment));
    }

    public static function append($alias, $key, $value)
    {
        return self::_send($alias, array('append', $key, $value));
    }

    public static function auth($alias, $pasword)
    {
        return self::_send($alias, array('Auth', $pasword));
    }

    public static function bgrewriteaof($alias)
    {
        return self::_send($alias, array('bgRewriteAOF'));
    }

    public static function bgsave($alias)
    {
        return self::_send($alias, array('bgSave'));
    }

    public static function blpop($alias, $keys, $timeout)
    {
        if (!is_array($keys)) $keys = func_get_args();
        else array_push($keys, $timeout);
        return self::_send($alias, array('BLPop', $keys));
    }

    public static function brpop($alias, $keys, $timeout)
    {
        if (!is_array($keys)) $keys = func_get_args();
        else array_push($keys, $timeout);
        return self::_send($alias,array('BRPop', $keys));
    }

    public static function brpoplpush($alias, $source, $destination, $timeout)
    {
        return self::_send($alias, array('BRPopLPush', $source, $destination, $timeout));
    }

    public static function config_get($alias, $pattern)
    {
        return self::_send($alias, array('CONFIG', 'GET', $pattern));
    }

    public static function config_set($alias, $parameter, $value)
    {
        return self::_send($alias, array('CONFIG', 'SET', $parameter, $value));
    }

    public static function config_resetstat($alias)
    {
        return self::_send($alias, array('CONFIG RESETSTAT'));
    }

    public static function dbsize($alias)
    {
        return self::_send($alias, array('dbsize'));
    }

    public static function decr($alias, $key)
    {
        return self::_send($alias, array('decr', $key));
    }

    public static function decrby($alias, $key, $decrement)
    {
        return self::_send($alias, array('DecrBy', $key, $decrement));
    }

    public static function exists($alias, $key)
    {
        return self::_send($alias, array('Exists', $key));
    }

    public static function expireat($alias, $key, $timestamp)
    {
        return self::_send($alias, array('Expireat', $key, $timestamp));
    }

    public static function flushall($alias)
    {
        return self::_send($alias, array('flushall'));
    }

    public static function getbit($alias, $key, $offset)
    {
        return self::_send($alias, array('GetBit', $key, $offset));
    }

    public static function getrange($alias, $key, $start, $end)
    {
        return self::_send($alias, array('getrange', $key, $start, $end));
    }

    public static function getset($alias, $key, $value)
    {
        return self::_send($alias, array('GetSet', $key, $value));
    }

    public static function hdel($alias, $key, $field)
    {
        if (!is_array($field)) $field = func_get_args();
        else array_unshift($field, $key);
        return self::_send($alias,array('hdel', $field));
    }

    public static function hexists($alias, $key, $field)
    {
        return self::_send($alias, array('hExists', $key, $field));
    }

    public static function hget($alias, $key, $field)
    {
        return self::_send($alias, array('hGet', $key, $field));
    }

    public static function hincrby($alias, $key, $field, $increment)
    {
        return self::_send($alias, array('hIncrBy', $key, $field, $increment));
    }

    public static function hkeys($alias, $key)
    {
        return self::_send($alias, array('hKeys', $key));
    }

    public static function hlen($alias, $key)
    {
        return self::_send($alias, array('hLen', $key));
    }

    public static function hmget($alias, $key, array $fields)
    {
        array_unshift($fields, $key);
        return self::_send($alias,array('hMGet', $fields));
    }

    public static function hmset($alias, $key, $fields)
    {
        $args[] = $key;
        foreach ($fields as $field => $value)
        {
            $args[] = $field;
            $args[] = $value;
        }
        return self::_send($alias,array('hMSet', $args));
    }

    public static function hsetnx($alias, $key, $field, $value)
    {
        return self::_send($alias, array('hSetNX', $key, $field, $value));
    }

    public static function hvals($alias, $key)
    {
        return self::_send($alias, array('hVals', $key));
    }

    public static function incr($alias, $key)
    {
        return self::_send($alias, array('Incr', $key));
    }

    public static function lindex($alias, $key, $index)
    {
        return self::_send($alias, array('LIndex', $key, $index));
    }

    public static function linsert($alias, $key, $after = true, $pivot, $value)
    {
        if ($after) $position = self::Position_AFTER;
        else $position = self::Position_BEFORE;
        return self::_send($alias, array('LInsert', $key, $position, $pivot, $value));
    }

    public static function llen($alias, $key)
    {
        return self::_send($alias, array('LLen', $key));
    }

    public static function lpop($alias, $key)
    {
        return self::_send($alias, array('LPop', $key));
    }

    public static function lpush($alias, $key, $value)
    {
        return self::_send($alias,array('lpush', $key, $value));
    }

    public static function lpushx($alias, $key, $value)
    {
        return self::_send($alias, array('LPushX', $key, $value));
    }

    public static function lrange($alias, $key, $start, $stop)
    {
        return self::_send($alias, array('LRange', $key, $start, $stop));
    }

    public static function lrem($alias, $key, $count, $value)
    {
        return self::_send($alias, array('LRem', $key, $count, $value));
    }

    public static function lset($alias, $key, $index, $value)
    {
        return self::_send($alias, array('LSet', $key, $index, $value));
    }

    public static function ltrim($alias, $key, $start, $stop)
    {
        return self::_send($alias, array('LTrim', $key, $start, $stop));
    }

    public static function mget($alias, $key)
    {
        if (!is_array($key)) $key = func_get_args();
        return self::_send($alias,array('MGet', $key));
    }

    public static function move($alias, $key, $db)
    {
        return self::_send($alias, array('Move', $key, $db));
    }

    public static function mset($alias, array $keys)
    {
        $q = array();
        foreach ($keys as $k => $v)
        {
            $q[] = $k;
            $q[] = $v;
        }
        return self::_send($alias,array('MSet', $q));
    }

    public static function msetnx($alias, array $keys)
    {
        $q = array();
        foreach ($keys as $k => $v)
        {
            $q[] = $k;
            $q[] = $v;
        }
        return self::_send($alias,array('MSetNX', $q));
    }

    public static function persist($alias, $key)
    {
        return self::_send($alias, array('Persist', $key));
    }

    public static function psubscribe($alias, $pattern)
    {
        return self::_send($alias, array('PSubscribe', $pattern));
    }

    public static function publish($alias, $channel, $message)
    {
        return self::_send($alias, array('Publish', $channel, $message));
    }

    public static function punsubscribe($alias, $patterns = null)
    {
        if (!empty($patterns))
        {
            if (!is_array($patterns)) $patterns = array($patterns);
            return self::__send($alias,array('PUnsubscribe', $patterns));
        }
        else return self::_send($alias, array('PUnsubscribe'));
    }

    public static function quit($alias)
    {
        return self::_send($alias, array('Quit'));
    }

    public static function rename($alias, $key, $newkey)
    {
        return self::_send($alias, array('Rename', $key, $newkey));
    }

    public static function renamenx($alias,$key, $newkey)
    {
        return self::_send($alias, array('RenameNX', $key, $newkey));
    }

    public static function rpop($alias, $key)
    {
        return self::_send($alias, array('RPop', $key));
    }

    public static function rpoplpush($alias, $source, $destination)
    {
        return self::_send($alias, array('RPopLPush', $source, $destination));
    }

    public static function rpush($alias, $key, $value)
    {
        #if (!is_array($value)) $value = func_get_args();
        #else array_unshift($value, $key);
        #print_r($value);
        //$value = array($key, $value);
        return self::_send($alias,array('rpush', $key, $value));
    }

    public static function rpushx($alias, $key, $value)
    {
        return self::_send($alias, array('RPushX', $key, $value));
    }

    public static function scard($alias, $key)
    {
        return self::_send($alias, array('sCard', $key));
    }

    public static function sdiff($alias,$key)
    {
        if (!is_array($key)) $key = func_get_args();
        return self::_send($alias,array('sDiff', $key));
    }

    public static function sdiffstore($alias,$destination, $key)
    {
        if (!is_array($key)) $key = func_get_args();
        else array_unshift($key, $destination);
        return self::_send($alias,array('sDiffStore', $key));
    }

    public static function select($alias,$index)
    {
        return self::_send($alias, array('Select', $index));
    }

    public static function setbit($alias,$key, $offset, $value)
    {
        return self::_send($alias, array('SetBit', $key, $offset, $value));
    }

    public static function setrange($alias,$key, $offset, $value)
    {
        return self::_send($alias, array('SetRange', $key, $offset, $value));
    }

    public static function sinter($alias,$key)
    {
        if (!is_array($key)) $key = func_get_args();
        return self::_send($alias,array('sInter', $key));
    }

    public static function sinterstore($alias,$destination, $key)
    {
        if (is_array($key)) array_unshift($key, $destination);
        else $key = func_get_args();
        return self::_send($alias,array('sInterStore', $key));
    }

    public static function slaveof($alias,$host, $port)
    {
        return self::_send($alias, array('SlaveOf', $host, $port));
    }

    public static function smove($alias,$source, $destination, $member)
    {
        return self::_send($alias, array('sMove', $source, $destination, $member));
    }

    public static function sort($alias,$key, $sort_rule)
    {
        return self::_send($alias, array('Sort', $key, $sort_rule));
    }

    public static function strlen($alias,$key)
    {
        return self::_send($alias, array('StrLen', $key));
    }

    public static function subscribe($alias,$channel)
    {
        if (!is_array($channel)) $channel = func_get_args();
        return self::_send($alias,array('Subscribe', $channel));
    }

    public static function sunion($alias,$key)
    {
        if (!is_array($key)) $key = func_get_args();
        return self::_send($alias,array('sUnion', $key));
    }

    public static function sunionstore($alias,$destination, $key)
    {
        if (!is_array($key)) $key = func_get_args();
        else array_unshift($key, $destination);
        return self::_send($alias,array('sUnionStore', $key));
    }

    public static function type($alias,$key)
    {
        return self::_send($alias, array('Type', $key));
    }

    public static function unsubscribe($alias,$channel = '')
    {
        $args = func_get_args();
        if (empty($args)) return self::_send($alias, array('Unsubscribe'));
        else
        {
            if (is_array($channel)) return self::_send($alias,array('Unsubscribe', $channel));
            else return self::_send($alias,array('Unsubscribe', $args));
        }
    }

    public static function unwatch($alias)
    {
        return self::_send($alias, array('Unwatch'));
    }

    public static function zadd($alias,$key, $score, $member = NULL)
    {
        if (!is_array($score)) $values = func_get_args();
        else
        {
            foreach ($score as $score_value => $member)
            {
                $values[] = $score_value;
                $values[] = $member;
            }
            array_unshift($values, $key);
        }
        return self::_send($alias,array('zadd', $values));
    }

    public static function zcard($alias,$key)
    {
        return self::_send($alias, array('zCard', $key));
    }

    public static function zcount($alias,$key, $min, $max)
    {
        return self::_send($alias, array('zCount', $key, $min, $max));
    }

    public static function zincrby($alias,$key, $increment, $member)
    {
        return self::_send($alias, array('zIncrBy', $key, $increment, $member));
    }

    public static function zinterstore($alias,$destination, array $keys, array $weights = null, $aggregate = null)
    {
        $destination = array($destination, count($keys));
        $destination = array_merge($destination, $keys);
        if (!empty($weights))
        {
            $destination[] = 'WEIGHTS';
            $destination   = array_merge($destination, $weights);
        }
        if (!empty($aggregate))
        {
            $destination[] = 'AGGREGATE';
            $destination[] = $aggregate;
        }
        return self::_send($alias,array('zInterStore', $destination));
    }

    public static function zrange($alias,$key, $start, $stop, $withscores = false)
    {
        if ($withscores) return self::_send($alias, array('zRange', $key, $start, $stop, self::WITHSCORES));
        else return self::_send($alias, array('zRange', $key, $start, $stop));
    }

    public static function zrangebyscore($alias,$key, $min, $max, $withscores = false, array $limit = null)
    {
        $args = array($key, $min, $max);
        if ($withscores) $args[] = self::WITHSCORES;
        if (!empty($limit))
        {
            $args[] = 'LIMIT';
            $args[] = $limit[0];
            $args[] = $limit[1];
        }
        return self::_send($alias,array('zRangeByScore', $args));
    }

    public static function zrank($alias,$key, $member)
    {
        return self::_send($alias, array('zRank', $key, $member));
    }

    public static function zrem($alias,$key, $member)
    {
        if (!is_array($member)) $member = func_get_args();
        else array_unshift($member, $key);
        return self::_send($alias,array('zrem', $member));
    }

    public static function zremrangebyrank($alias,$key, $start, $stop)
    {
        return self::_send($alias, array('zRemRangeByRank', $key, $start, $stop));
    }

    public static function zremrangebyscore($alias,$key, $min, $max)
    {
        return self::_send($alias, array('zRemRangeByScore', $key, $min, $max));
    }

    public static function zrevrange($alias,$key, $start, $stop, $withscores = false)
    {
        if ($withscores) return self::_send($alias, array('zRevRange', $key, $start, $stop, self::WITHSCORES));
        else return self::_send($alias, array('zRevRange', $key, $start, $stop));
    }

    public static function zrevrangebyscore($alias,$key, $max, $min, $withscores = false, array $limit = null)
    {
        $args = array($key, $max, $min);
        if ($withscores) $args[] = self::WITHSCORES;
        if (!empty($limit))
        {
            $args[] = 'LIMIT';
            $args[] = $limit[0];
            $args[] = $limit[1];
        }
        return self::_send($alias,array('zRevRangeByScore', $args));
    }

    public static function zrevrank($alias,$key, $member)
    {
        return self::_send($alias, array('zRevRank', $key, $member));
    }

    public static function zscore($alias,$key, $member)
    {
        return self::_send($alias, array('zScore', $key, $member));
    }

    public static function zunionstore($alias,$destination, array $keys, array $weights = null, $aggregate = null)
    {
        $destination = array($destination, count($keys));
        $destination = array_merge($destination, $keys);
        if (!empty($weights))
        {
            $destination[] = 'WEIGHTS';
            $destination   = array_merge($destination, $weights);
        }
        if (!empty($aggregate))
        {
            $destination[] = 'AGGREGATE';
            $destination[] = $aggregate;
        }
        return self::_send($alias,array('zUnionStore', $destination));
    }
}

?>
