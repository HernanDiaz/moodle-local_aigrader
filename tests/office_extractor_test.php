<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_aigrader\extractor;

/**
 * Tests for the PowerPoint and OpenDocument extractors, and for office
 * documents inside .zip submissions. Fixtures are built on the fly with
 * the minimal XML each format needs.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\extractor\pptx_extractor
 * @covers     \local_aigrader\extractor\odf_extractor
 * @covers     \local_aigrader\extractor\zip_extractor
 * @covers     \local_aigrader\extractor\docx_extractor
 */
final class office_extractor_test extends \advanced_testcase {
    /** DrawingML and PresentationML namespaces used by the slide fixtures. */
    private const PML = 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        . 'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

    /** Relationship type prefix. */
    private const RELTYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';

    /**
     * Slides follow the order of the presentation, not of the file names;
     * speaker notes follow their slide; slide-number fields are dropped.
     */
    public function test_pptx_follows_presentation_order_with_notes(): void {
        $path = $this->make_zip([
            'ppt/presentation.xml' => '<p:presentation ' . self::PML . '><p:sldIdLst>'
                . '<p:sldId id="256" r:id="rId3"/><p:sldId id="257" r:id="rId2"/>'
                . '</p:sldIdLst></p:presentation>',
            'ppt/_rels/presentation.xml.rels' => $this->rels([
                'rId2' => ['slide', 'slides/slide1.xml'],
                'rId3' => ['slide', 'slides/slide2.xml'],
            ]),
            'ppt/slides/slide1.xml' => $this->slide(['Moved to the end', 'R&amp;D results']),
            'ppt/slides/slide2.xml' => $this->slide(['Opening slide']),
            'ppt/slides/_rels/slide1.xml.rels' => $this->rels([
                'rId1' => ['notesSlide', '../notesSlides/notesSlide7.xml'],
            ]),
            'ppt/notesSlides/notesSlide7.xml' => $this->slide(['Say this aloud']),
        ]);

        $text = pptx_extractor::extract_path($path);

        $this->assertNotNull($text);
        $this->assertLessThan(strpos($text, 'Moved to the end'), strpos($text, 'Opening slide'));
        $this->assertStringContainsString("--- Slide 1 ---\nOpening slide", $text);
        $this->assertStringContainsString("--- Slide 2 ---\nMoved to the end\nR&D results\nSpeaker notes:\nSay this aloud", $text);
        $this->assertStringNotContainsString('99', $text, 'Slide-number fields must be dropped.');
    }

    /**
     * Without a readable presentation.xml the slides are read by the number
     * in their file name (slide2 before slide10).
     */
    public function test_pptx_falls_back_to_numeric_file_order(): void {
        $path = $this->make_zip([
            'ppt/slides/slide10.xml' => $this->slide(['Tenth']),
            'ppt/slides/slide2.xml' => $this->slide(['Second']),
        ]);

        $text = pptx_extractor::extract_path($path);

        $this->assertSame("--- Slide 1 ---\nSecond\n\n--- Slide 2 ---\nTenth", $text);
    }

    /**
     * A file that is not a presentation gives null, not an exception.
     */
    public function test_pptx_malformed_returns_null(): void {
        $path = make_request_directory() . '/broken.pptx';
        file_put_contents($path, 'this is not a zip');
        $this->assertNull(pptx_extractor::extract_path($path));
        $this->assertNull(pptx_extractor::extract_path($this->make_zip(['ppt/slides/slide1.xml' => $this->slide([])])));
    }

    /**
     * ODT: headings and paragraphs are kept with their spacing; tracked
     * deletions and comments are not.
     */
    public function test_odt_extracts_body_text_only(): void {
        $path = $this->make_zip([
            'content.xml' => $this->odf_content('<office:text>'
                . '<text:tracked-changes><text:changed-region><text:deletion>'
                . '<text:p>DELETED WORDS</text:p></text:deletion></text:changed-region></text:tracked-changes>'
                . '<text:h text:outline-level="1">Introduction</text:h>'
                . '<text:p>Hello<text:s text:c="3"/>world<text:tab/>tabbed'
                . '<office:annotation><dc:creator>Reviewer</dc:creator><text:p>A COMMENT</text:p></office:annotation>'
                . '</text:p>'
                . '<text:p>Second<text:line-break/>line &amp; more</text:p>'
                . '</office:text>'),
        ]);

        $text = odf_extractor::extract_path($path);

        $this->assertSame("Introduction\nHello   world\ttabbed\nSecond\nline & more", $text);
    }

    /**
     * ODP: one block per slide with its speaker notes.
     */
    public function test_odp_splits_slides_and_notes(): void {
        $path = $this->make_zip([
            'content.xml' => $this->odf_content('<office:presentation>'
                . '<draw:page draw:name="page1"><draw:frame><draw:text-box><text:p>Title slide</text:p></draw:text-box></draw:frame>'
                . '<presentation:notes><draw:frame><draw:text-box><text:p>Welcome everyone</text:p></draw:text-box></draw:frame></presentation:notes>'
                . '</draw:page>'
                . '<draw:page draw:name="page2"><draw:frame><draw:text-box><text:p>Results</text:p></draw:text-box></draw:frame></draw:page>'
                . '</office:presentation>'),
        ]);

        $text = odf_extractor::extract_path($path);

        $this->assertStringContainsString("--- Slide 1 ---\nTitle slide", $text);
        $this->assertStringContainsString("Speaker notes:\nWelcome everyone", $text);
        $this->assertStringContainsString("--- Slide 2 ---\nResults", $text);
    }

    /**
     * Office documents inside a .zip submission are read, not skipped as binaries.
     */
    public function test_zip_reads_office_documents_inside(): void {
        $this->resetAfterTest();

        $docx = $this->make_zip([
            'word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:p><w:r><w:t>Report body text</w:t></w:r></w:p></w:body></w:document>',
        ]);
        $pptx = $this->make_zip(['ppt/slides/slide1.xml' => $this->slide(['Slide inside the zip'])]);
        $odt = $this->make_zip(['content.xml' => $this->odf_content('<office:text><text:p>Libre text</text:p></office:text>')]);
        $outer = $this->make_zip([
            'project/report.docx' => file_get_contents($docx),
            'project/slides.pptx' => file_get_contents($pptx),
            'project/notes.odt' => file_get_contents($odt),
            'project/main.py' => "print('hello')\n",
        ]);
        $file = get_file_storage()->create_file_from_pathname([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_aigrader',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'project.zip',
        ], $outer);

        $result = zip_extractor::extract_file($file);

        $this->assertStringContainsString("--- project/report.docx (Word document) ---\nReport body text", $result['text']);
        $this->assertStringContainsString("--- project/slides.pptx (PowerPoint presentation) ---\n--- Slide 1 ---\nSlide inside the zip", $result['text']);
        $this->assertStringContainsString("--- project/notes.odt (OpenDocument text) ---\nLibre text", $result['text']);
        $this->assertStringContainsString("print('hello')", $result['text']);
    }

    /**
     * Build a zip file from entry name => content.
     *
     * @param array $entries Zip entries, entry name => content.
     * @return string Path of the zip file.
     */
    private function make_zip(array $entries): string {
        $path = make_request_directory() . '/' . uniqid('fixture_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        return $path;
    }

    /**
     * A slide (or notes page) with one paragraph per line, plus a
     * slide-number field that must not reach the text.
     *
     * @param string[] $paragraphs Paragraph texts (XML-escaped).
     * @return string Slide XML.
     */
    private function slide(array $paragraphs): string {
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $body .= '<a:p><a:r><a:t>' . $paragraph . '</a:t></a:r></a:p>';
        }
        $body .= '<a:p><a:fld id="{1}" type="slidenum"><a:t>99</a:t></a:fld></a:p>';
        return '<p:sld ' . self::PML . '><p:cSld><p:spTree><p:sp><p:txBody>' . $body
            . '</p:txBody></p:sp></p:spTree></p:cSld></p:sld>';
    }

    /**
     * A relationships part.
     *
     * @param array $rels Relationship id => [type suffix, target].
     * @return string Relationships XML.
     */
    private function rels(array $rels): string {
        $xml = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($rels as $id => [$type, $target]) {
            $xml .= '<Relationship Id="' . $id . '" Type="' . self::RELTYPE . $type . '" Target="' . $target . '"/>';
        }
        return $xml . '</Relationships>';
    }

    /**
     * An OpenDocument content.xml around a body.
     *
     * @param string $body Content of office:body.
     * @return string content.xml.
     */
    private function odf_content(string $body): string {
        return '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" '
            . 'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" '
            . 'xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" '
            . 'xmlns:presentation="urn:oasis:names:tc:opendocument:xmlns:presentation:1.0" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<office:automatic-styles><style:style xmlns:style="s"/></office:automatic-styles>'
            . '<office:body>' . $body . '</office:body></office:document-content>';
    }
}
