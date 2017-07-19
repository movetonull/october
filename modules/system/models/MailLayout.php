<?php namespace System\Models;

use File;
use View;
use Model;
use System\Classes\MailManager;
use October\Rain\Mail\MailParser;
use ApplicationException;

/**
 * Mail layout
 *
 * @package october\system
 * @author Alexey Bobkov, Samuel Georges
 */
class MailLayout extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    protected $table = 'system_mail_layouts';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'code'                  => 'required|unique:system_mail_layouts',
        'name'                  => 'required',
        'content_html'          => 'required',
    ];

    public static $codeCache;

    public function beforeDelete()
    {
        if ($this->is_locked) {
            throw new ApplicationException('Cannot delete this template because it is locked');
        }
    }

    public static function listCodes()
    {
        if (self::$codeCache !== null) {
            return self::$codeCache;
        }

        return self::$codeCache = self::lists('id', 'code');
    }

    public static function getIdFromCode($code)
    {
        return array_get(self::listCodes(), $code);
    }

    /**
     * Loops over each mail layout and ensures the system has a layout,
     * if the layout does not exist, it will create one.
     * @return void
     */
    public static function createLayouts()
    {
        $dbLayouts = self::lists('code', 'code');

        $definitions = MailManager::instance()->listRegisteredLayouts();
        foreach ($definitions as $code => $path) {
            if (array_key_exists($code, $dbLayouts)) {
                continue;
            }

            self::createLayoutFromFile($code, $path);
        }
    }

    /**
     * Creates a layout using the contents of a specified file.
     * @param  string $code  New Layout code
     * @param  string $viewPath  View path
     * @return void
     */
    public static function createLayoutFromFile($code, $viewPath)
    {
        $sections = self::getTemplateSections($viewPath);

        $name = array_get($sections, 'settings.name', '???');

        $css = 'a, a:hover {
            text-decoration: none;
            color: #0862A2;
            font-weight: bold;
        }

        td, tr, th, table {
            padding: 0px;
            margin: 0px;
        }

        p {
            margin: 10px 0;
        }';

        self::create([
            'is_locked'    => true,
            'name'         => $name,
            'code'         => $code,
            'content_css'  => $css,
            'content_html' => array_get($sections, 'html'),
            'content_text' => array_get($sections, 'text')
        ]);
    }

    protected static function getTemplateSections($code)
    {
        return MailParser::parse(File::get(View::make($code)->getPath()));
    }
}
