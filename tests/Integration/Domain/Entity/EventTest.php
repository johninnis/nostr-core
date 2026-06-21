<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Domain\Entity;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    public function testCalculateIdAndVerifyHandleParagraphSeparatorInContent(): void
    {
        $event = Event::fromArray([
            'id' => 'ebb6b3d01d4f5ade21554c70ccc18d663a9765573ba42eac6ff4c504a0b81111',
            'pubkey' => '910a1d5c845b9eb04787fa339651e05883eca8045d804d5a40e9d7e2737ff460',
            'created_at' => 1773410433,
            'kind' => 0,
            'tags' => [
                ['proxy', 'https://infosec.exchange/users/spamhaus', 'activitypub'],
                ['client', 'Mostr', '31990:6be38f8c63df7dbf84db7ec4a6e6fbbd8d19dca3b980efad18585c46f04b26f9:mostr', 'wss://relay.ditto.pub'],
            ],
            'content' => '{"name":"The Spamhaus Project","about":"Spamhaus strengthens trust and safety for the Internet. Advocating for change through sharing reliable intelligence and expertise. As the authority on IP and domain reputation data, we are trusted across the industry because of our strong ethics, impartiality, and quality of actionable data. This data not only protects but also provides signal and insight across networks and email worldwide. '."\u{2029}".'With over two decades of experience, our researchers and threat hunters focus on exposing malicious activity to make the internet a better place for everyone. A wide range of industries, including leading global technology companies, use Spamhaus\' data; currently protecting over 4.5 billion mailboxes worldwide.","picture":"https://media.infosec.exchange/infosec.exchange/accounts/avatars/109/320/853/817/139/353/original/9bf10cbd9f875bcd.jpeg","banner":"https://media.infosec.exchange/infosec.exchange/accounts/headers/109/320/853/817/139/353/original/04fec027cdcf80eb.jpg","nip05":"spamhaus@infosec-exchange.mostr.pub","fields":[["Website","https://www.spamhaus.org"],["Threat Intel Community","https://submit.spamhaus.org"],["LinkedIn","https://www.linkedin.com/company/the-spamhaus-project"],["Twitter","https://twitter.com/spamhaus"]]}',
            'sig' => '7614f8586aacb36e5a501d2f11b0501faa070ab0d90434f7e81bd7dbde4cabb935e80a0064f9db2ba8db2f673ec510ade473a855d1407572ba53873fc13f3290',
        ]);

        $this->assertNotNull($event);
        $this->assertSame(
            'ebb6b3d01d4f5ade21554c70ccc18d663a9765573ba42eac6ff4c504a0b81111',
            $event->calculateId()->toHex(),
            'calculateId() must emit U+2029 verbatim per NIP-01'
        );
        $this->assertTrue($event->verify(CryptoFixtures::signer()), 'verify() must succeed for an event whose content contains U+2029');
    }
}
