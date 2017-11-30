#!/usr/bin/perl

use strict;
use warnings;

BEGIN {
  use Cwd qw( realpath );
  use File::Basename;
  my $basedir = dirname(realpath(__FILE__));
  unshift @INC, $basedir . '/perl-vendor';
}

use SNMP::MIB::Compiler;

my $fh = new FileHandle '< -';
unless (defined $fh) {
  print "Error: can't open STDIN: $!\n";
  exit(1);
}

my $mib = new SNMP::MIB::Compiler;
$mib->{'make_dump'}  = 0;
$mib->{'use_dump'}   = 0;
$mib->{'do_imports'} = 0;

my $s = Stream->new($fh);
$mib->{'stream'} = $s;
my $r = $mib->parse_Module();
delete $mib->{'stream'};
$fh->close;

unless ($r) {
  print "Unable to parse MIB\n";
  exit(1);
}

$mib->create_tree();

use JSON;
print to_json {
   'name'    => $mib->{'name'},
   'imports' => $mib->{'imports'},
   'nodes'   => $mib->{'nodes'},
   'types'   => $mib->{'types'},
   'macros'  => $mib->{'macros'},
   'tree'    => $mib->{'tree'},
   'traps'   => $mib->{'traps'},
};
