<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

class m260430_094400_rename_cookie_banner_handle extends Migration
{
    private const OLD_HANDLE = 'cookie-banner';
    private const NEW_HANDLE = '_statik-cookie-banner';

    public function safeUp(): bool
    {
        $oldDb = (new Query())
            ->from('{{%plugins}}')
            ->where(['handle' => self::OLD_HANDLE])
            ->one();

        $newDb = (new Query())
            ->from('{{%plugins}}')
            ->where(['handle' => self::NEW_HANDLE])
            ->one();

        if ($oldDb && !$newDb) {
            $this->update(
                '{{%plugins}}',
                ['handle' => self::NEW_HANDLE],
                ['handle' => self::OLD_HANDLE]
            );
        }

        $this->update(
            '{{%plugins}}',
            ['licenseKey' => null],
            ['and', ['handle' => self::NEW_HANDLE], ['not', ['licenseKey' => null]]]
        );

        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            Craft::info(
                'cookie-banner handle rename: skipping project config writes (allowAdminChanges is false).',
                __METHOD__
            );
            return true;
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $oldYaml = $projectConfig->get('plugins.' . self::OLD_HANDLE);
        $newYaml = $projectConfig->get('plugins.' . self::NEW_HANDLE);

        if ($oldYaml && !$newYaml) {
            $projectConfig->set('plugins.' . self::NEW_HANDLE, $oldYaml, force: true);
            $projectConfig->remove('plugins.' . self::OLD_HANDLE);
        }

        if ($projectConfig->get('plugins.' . self::NEW_HANDLE . '.licenseKey') !== null) {
            $projectConfig->remove('plugins.' . self::NEW_HANDLE . '.licenseKey');
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260430_094400_rename_cookie_banner_handle cannot be reverted.\n";
        return false;
    }
}
