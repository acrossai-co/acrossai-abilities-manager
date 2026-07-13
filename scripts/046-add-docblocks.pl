#!/usr/bin/env perl
# 046-add-docblocks.pl — Feature 046 docblock injector.
# Reads a list of file paths from stdin (one per line, absolute or repo-relative)
# and injects file/class/method docblocks where missing.
# Idempotent per file. Per-file synchronous writes.

use strict;
use warnings;

while (my $path = <STDIN>) {
    chomp $path;
    next unless $path && -f $path && $path =~ /\.php$/;
    process($path);
}
print "done\n";

sub process {
    my ($path) = @_;
    open(my $fh, '<', $path) or die "read $path: $!";
    my @lines = <$fh>;
    close $fh;

    my $orig = join('', @lines);

    # Derive @subpackage from the file's namespace line.
    my $subpackage = 'Includes\\Abilities';
    for my $l (@lines) {
        if ($l =~ /^namespace\s+AcrossAI_Abilities_Manager\\([^;]+);/) {
            $subpackage = $1;
            last;
        }
    }

    # 1. File-level docblock (only if line 2 is NOT already a docblock start).
    if (@lines >= 2 && $lines[0] =~ /^<\?php\s*$/ && $lines[1] !~ m{^\s*/\*\*}) {
        my $dblock = "/**\n"
                   . " * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).\n"
                   . " *\n"
                   . " * \@license    GPL-2.0-or-later\n"
                   . " * \@package    AcrossAI_Abilities_Manager\n"
                   . " * \@subpackage $subpackage\n"
                   . " * \@since      0.1.0\n"
                   . " */\n\n";
        splice(@lines, 1, 0, $dblock);
    }

    # Walk & inject method / class docblocks.
    for (my $i = 0; $i < @lines; $i++) {
        my $line = $lines[$i];

        # Class docblock — before "class Foo extends Ability_Definition"
        if ($line =~ /^(?:final\s+)?class\s+(\w+)\s+extends\s+Ability_Definition/) {
            my $cls = $1;
            if (!_prev_is_docblock_end(\@lines, $i)) {
                my $dblock = "/**\n * $cls ability class (absorbed).\n */\n";
                splice(@lines, $i, 0, $dblock);
                $i++;
                next;
            }
        }

        # `final class Category_Registrar {` — before it
        if ($line =~ /^(?:final\s+)?class\s+Category_Registrar\s*\{/) {
            if (!_prev_is_docblock_end(\@lines, $i)) {
                my $dblock = "/**\n * Category_Registrar for the absorbed ability inventory.\n */\n";
                splice(@lines, $i, 0, $dblock);
                $i++;
                next;
            }
        }

        # protected function ability(): array {
        if ($line =~ /^\tprotected function ability\(\)\s*:\s*array\s*\{/) {
            if (!_prev_is_docblock_end(\@lines, $i)) {
                my $dblock = "\t/**\n"
                           . "\t * Full ability spec for wp_register_ability().\n"
                           . "\t *\n"
                           . "\t * \@return array\n"
                           . "\t */\n";
                splice(@lines, $i, 0, $dblock);
                $i++;
                next;
            }
        }

        # public function execute( array $input = array() ): array {
        if ($line =~ /^\tpublic function execute\(/) {
            if (!_prev_is_docblock_end(\@lines, $i)) {
                my $dblock = "\t/**\n"
                           . "\t * Execute the ability.\n"
                           . "\t *\n"
                           . "\t * \@param array \$input Ability input payload.\n"
                           . "\t * \@return array\n"
                           . "\t */\n";
                splice(@lines, $i, 0, $dblock);
                $i++;
                next;
            }
        }

        # public function register(): void {
        if ($line =~ /^\tpublic function register\(\)\s*:\s*void\s*\{/) {
            if (!_prev_is_docblock_end(\@lines, $i)) {
                my $dblock = "\t/**\n"
                           . "\t * Register the ability category with the WP Abilities API.\n"
                           . "\t *\n"
                           . "\t * \@return void\n"
                           . "\t */\n";
                splice(@lines, $i, 0, $dblock);
                $i++;
                next;
            }
        }

        # public static function instance(): self {
        if ($line =~ /^\tpublic static function instance\(\)\s*:\s*self\s*\{/) {
            if (!_prev_is_docblock_end(\@lines, $i)) {
                my $dblock = "\t/**\n"
                           . "\t * Return the singleton instance.\n"
                           . "\t *\n"
                           . "\t * \@return self\n"
                           . "\t */\n";
                splice(@lines, $i, 0, $dblock);
                $i++;
                next;
            }
        }

        # private function __construct() {}
        if ($line =~ /^\tprivate function __construct\(\)\s*\{\}/) {
            if (!_prev_is_docblock_end(\@lines, $i)) {
                my $dblock = "\t/**\n"
                           . "\t * Private constructor \x{2014} access via instance().\n"
                           . "\t */\n";
                splice(@lines, $i, 0, $dblock);
                $i++;
                next;
            }
        }
    }

    my $new = join('', @lines);
    if ($new ne $orig) {
        open(my $out, '>', $path) or die "write $path: $!";
        print $out $new;
        close $out;
    }
}

# Look backward past blank lines. Return true if the nearest non-blank line
# ends a docblock (`*/`).
sub _prev_is_docblock_end {
    my ($lines, $i) = @_;
    my $j = $i - 1;
    while ($j >= 0 && $lines->[$j] =~ /^\s*$/) { $j--; }
    return $j >= 0 && $lines->[$j] =~ m{\*/\s*$};
}
