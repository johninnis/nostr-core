<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\ContentReferenceTagBuilder;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Infrastructure\Adapter\Bech32EncoderAdapter;
use Innis\Nostr\Core\Infrastructure\Adapter\ContentReferenceExtractorAdapter;
use PHPUnit\Framework\TestCase;

final class ContentReferenceTagBuilderTest extends TestCase
{
    private ContentReferenceTagBuilder $tagBuilder;

    protected function setUp(): void
    {
        $bech32 = new Bech32EncoderAdapter();
        $extractor = new ContentReferenceExtractorAdapter($bech32);
        $this->tagBuilder = new ContentReferenceTagBuilder($extractor);
    }

    public function testBuildTagsExtractsTagsFromNostrReferences(): void
    {
        $content = EventContent::fromString(
            'Some tests:'."\n\n"
            .'nostr:nprofile1qqsf03c2gsmx5ef4c9zmxvlew04gdh7u94afnknp33qvv3c94kvwxgspp4mhxue69uhkummn9ekx7mqpzfmhxue69uhhqatjwpkx2urpvuhx2ucpz3mhxue69uhhyetvv9ujuerpd46hxtnfduq3vamnwvaz7tmjv4kxz7fwdehhxarj9e3xzmnyqywhwumn8ghj7mn0wd68yttsw43zuam9d3kx7unyv4ezumn9wsq3gamnwvaz7tmjv4kxz7fwdehhxarj9eskjqg4waehxw309ahx7um5wghrq7p4xqh8getrdqq3xamnwvaz7tmjv4kxz7tpvfkx2tn0wfnszynhwden5te0danxvcmgv95kutnsw43q0g3ycy'."\n"
            .'nostr:note1kq58dtv4fcddn7g9fzl4595g79qgr8k5st2c6vv94x9xugv64r3qqmrmff'."\n"
            .'nostr:npub180cvv07tjdrrgpa0j7j7tmnyl2yr6yr7l8j4s3evf6u64th6gkwsyjh6w6'."\n"
            .'nostr:nevent1qqsts3r4v3ptcwhrfurz2h9y833mvn2z20ackj9lgwvc7d007e6khcqpzamhxue69uhkzarvv9ejumn0wd68ytnvv9hxgtcpzpmhxue69uhk2tnwdaejumr0dshsz9nhwden5te0v4jx2m3wdehhxarj9ekxzmny9uq3uamnwvaz7tmxv4jkguewdehhxarj9e3xzmny9acx7ur4d3shyqtxwaehxw309anxjmr5v4ezumn0wd68ytnhd9hx2tmwwp6kyvtpxdc8vam9xfcrxa3hd4hx573kdpkx2d3nwgmrywrhdsuhwdfkxashwdm4xgekv7n3wvcrvvnkx4m8zcm3w96nxum8dqen7cnjdaskgcmpwd6r6arjw4jszrnhwden5te0dehhxtnvdakz7qguwaehxw309ahx7um5wgkhqatz9eek2mtfwdhkctnyv4mz7qg7waehxw309ahx7um5wgkhqatz9emk2mrvdaexgetj9ehx2ap0qyghwumn8ghj7mn0wd68ytnhd9hx2tcprfmhxue69uhhqatjv9mxjerp9ehx7um5wghxcctwvshsz9thwden5te0wfjkccte9ejxzmt4wvhxjme0qythwumn8ghj7un9d3shjtnwdaehgu3wvfskuep0qyshwumn8ghj7un9d3shjtnwdaehgu3wvfskuep0wfjhxarjd93hgetyqy08wumn8ghj7un9d3shjtnwdaehgu3wvfskuep0w3e82um5v4jqz9rhwden5te0wfjkcctev93xcefwdaexwtc8ewaeu'."\n"
            .'nostr:nevent1qqsq5zzu9ezhgq6es36jgg94wxsa2xh55p4tfa56yklsvjemsw7vj3cpp4mhxue69uhkummn9ekx7mqpr4mhxue69uhkummnw3ez6ur4vgh8wetvd3hhyer9wghxuet5qy8hwumn8ghj7mn0wd68ytnddaksz9rhwden5te0dehhxarj9ehhsarj9ejx2aspzfmhxue69uhk7enxvd5xz6tw9ec82cspz3mhxue69uhhyetvv9ujuerpd46hxtnfduq3vamnwvaz7tmjv4kxz7fwdehhxarj9e3xzmnyqy28wumn8ghj7un9d3shjtnwdaehgu3wvfnsz9nhwden5te0wfjkccte9ec8y6tdv9kzumn9wspzpn92tr3hexwgt0z7w4qz3fcch4ryshja8jeng453aj4c83646jxvxkyvs4'
        );

        $tags = $this->tagBuilder->buildTags($content);
        $tagArrays = $tags->toArray();

        $nprofilePubkey = '97c70a44366a6535c145b333f973ea86dfdc2d7a99da618c40c64705ad98e322';
        $noteId = 'b02876ad954e1ad9f90548bf5a1688f140819ed482d58d3185a98a6e219aa8e2';
        $npubPubkey = '3bf0c63fcb93463407af97a5e5ee64fa883d107ef9e558472c4eb9aaaefa459d';
        $neventOneId = 'b844756442bc3ae34f06255ca43c63b64d4253fb8b48bf43998f35eff6756be0';
        $neventTwoId = '0a085c2e4574035984752420b571a1d51af4a06ab4f69a25bf064b3b83bcc947';
        $neventTwoAuthor = 'ccaa58e37c99c85bc5e754028a718bd46485e5d3cb3345691ecab83c755d48cc';

        $pTags = array_filter($tagArrays, static fn (array $t) => 'p' === $t[0]);
        $eTags = array_filter($tagArrays, static fn (array $t) => 'e' === $t[0]);
        $qTags = array_filter($tagArrays, static fn (array $t) => 'q' === $t[0]);

        $pTagValues = array_map(static fn (array $t) => $t[1], $pTags);
        $eTagValues = array_map(static fn (array $t) => $t[1], $eTags);
        $qTagValues = array_map(static fn (array $t) => $t[1], $qTags);

        $this->assertContains($nprofilePubkey, $pTagValues, 'nprofile should produce a p tag');
        $this->assertContains($npubPubkey, $pTagValues, 'npub should produce a p tag');
        $this->assertContains($neventTwoAuthor, $pTagValues, 'nevent with author should produce a p tag');
        $this->assertCount(3, $pTags, 'should have exactly 3 p tags');

        $this->assertContains($noteId, $eTagValues, 'note should produce an e tag');
        $this->assertContains($neventOneId, $eTagValues, 'nevent without author should produce an e tag');
        $this->assertContains($neventTwoId, $eTagValues, 'nevent with author should produce an e tag');
        $this->assertCount(3, $eTags, 'should have exactly 3 e tags');

        $noteETag = array_values(array_filter($tagArrays, static fn (array $t) => 'e' === $t[0] && $t[1] === $noteId));
        $this->assertSame('mention', $noteETag[0][3], 'note e tag should have mention marker');

        $this->assertContains($noteId, $qTagValues, 'note should produce a q tag');
        $this->assertContains($neventOneId, $qTagValues, 'nevent without author should produce a q tag');
        $this->assertContains($neventTwoId, $qTagValues, 'nevent with author should produce a q tag');
        $this->assertCount(3, $qTags, 'should have exactly 3 q tags');

        $neventTwoQTag = array_values(array_filter($tagArrays, static fn (array $t) => 'q' === $t[0] && $t[1] === $neventTwoId));
        $this->assertSame($neventTwoAuthor, $neventTwoQTag[0][3], 'nevent q tag should include author pubkey');
    }
}
