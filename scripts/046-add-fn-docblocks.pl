#!/usr/bin/env perl
# 046-add-fn-docblocks.pl — general-purpose function-docblock injector.
# Reads file paths from stdin, one per line. For every function that lacks a
# preceding docblock, synthesises one from the signature (@param + @return).
# Idempotent: skips functions that already have a docblock end-marker in the
# preceding non-blank line.

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
    my @out;
    my $i = 0;

    while ($i < @lines) {
        my $line = $lines[$i];

        if ($line =~ /^(\s*)((?:public|protected|private)(?:\s+static)?\s+function\s+(\w+)\s*\(([^)]*)\)(?:\s*:\s*[?a-zA-Z0-9_\\\|]+)?)\s*\{?\s*$/) {
            my ($indent, $sig, $fnname, $params_str) = ($1, $2, $3, $4);

            # If prev non-blank line ends with */ we already have a docblock.
            if (_prev_is_docblock_end(\@out)) {
                push @out, $line;
                $i++;
                next;
            }

            # Skip if this is a class constant or property assignment (safety).
            if ($fnname eq '' || $fnname =~ /^__?/) {
                # __construct / __toString etc — synthesise minimal docblock.
            }

            my @params;
            if ($params_str && $params_str !~ /^\s*$/) {
                for my $chunk (split /\s*,\s*/, $params_str) {
                    if ($chunk =~ /(\$\w+)/) {
                        my $var = $1;
                        my $type = 'mixed';
                        if ($chunk =~ /^\s*array\b/) { $type = 'array'; }
                        elsif ($chunk =~ /^\s*string\b/) { $type = 'string'; }
                        elsif ($chunk =~ /^\s*\?string\b/) { $type = 'string|null'; }
                        elsif ($chunk =~ /^\s*int\b/) { $type = 'int'; }
                        elsif ($chunk =~ /^\s*\?int\b/) { $type = 'int|null'; }
                        elsif ($chunk =~ /^\s*bool\b/) { $type = 'bool'; }
                        elsif ($chunk =~ /^\s*float\b/) { $type = 'float'; }
                        elsif ($chunk =~ /^\s*(\\?[A-Za-z_][A-Za-z0-9_\\]*)\b/) { $type = $1; }
                        push @params, [$type, $var];
                    }
                }
            }

            my $return_type = '';
            if ($sig =~ /:\s*([?a-zA-Z0-9_\\\|]+)\s*$/) {
                $return_type = $1;
            }

            my $dblock = "$indent/**\n";
            $dblock .= "$indent * " . _humanize($fnname) . "\n";
            if (@params) {
                $dblock .= "$indent *\n";
                for my $p (@params) {
                    my ($t, $v) = @$p;
                    $dblock .= "$indent * \@param $t $v\n";
                }
            }
            if ($return_type && $return_type ne 'void') {
                $dblock .= "$indent *\n" unless @params;
                $dblock .= "$indent * \@return $return_type\n";
            } elsif ($return_type eq 'void') {
                $dblock .= "$indent *\n" unless @params;
                $dblock .= "$indent * \@return void\n";
            }
            $dblock .= "$indent */\n";

            push @out, $dblock, $line;
            $i++;
            next;
        }

        push @out, $line;
        $i++;
    }

    my $new = join('', @out);
    if ($new ne $orig) {
        open(my $out, '>', $path) or die "write $path: $!";
        print $out $new;
        close $out;
    }
}

sub _humanize {
    my ($name) = @_;
    return 'Constructor.' if $name eq '__construct';
    my $s = $name;
    $s =~ s/_/ /g;
    $s = ucfirst($s);
    return "$s.";
}

sub _prev_is_docblock_end {
    my ($lines) = @_;
    my $j = @$lines - 1;
    while ($j >= 0 && $lines->[$j] =~ /^\s*$/) { $j--; }
    return $j >= 0 && $lines->[$j] =~ m{\*/\s*$};
}
