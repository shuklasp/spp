package SPP;

use strict;
use warnings;
use JSON;
use File::Spec;
use Cwd qw(abs_path);

my $bridge_config = undef;
my $spp_cli_path = undef;

sub init {
    my ($config_path) = @_;

    # Find base dir by navigating up from this module's directory
    my $module_dir = abs_path(__FILE__);
    $module_dir =~ s/[\\\/][^\\\/]+$//; # remove SPP.pm
    my $base_dir = abs_path(File::Spec->catdir($module_dir, '..', '..', '..'));
    
    $spp_cli_path = File::Spec->catfile($base_dir, 'spp.php');

    unless ($config_path) {
        $config_path = File::Spec->catfile($base_dir, 'var', 'shared', 'bridge_config.json');
        unless (-e $config_path) {
            $config_path = File::Spec->catfile($base_dir, '..', 'var', 'shared', 'bridge_config.json');
        }
    }

    if (-e $config_path) {
        open my $fh, '<', $config_path or die "Could not open $config_path: $!";
        my $json_text = do { local $/; <$fh> };
        close $fh;
        $bridge_config = decode_json($json_text);
    } else {
        die "SPP Bridge configuration not found at $config_path";
    }
}

sub get_config {
    my ($key, $section) = @_;
    $section ||= 'bridge_settings';
    
    init() unless $bridge_config;
    
    return $bridge_config->{$section}->{$key} if exists $bridge_config->{$section} && ref $bridge_config->{$section} eq 'HASH';
    return undef;
}

sub call_php {
    my ($class_name, $method_name, $args) = @_;
    $args ||= [];
    
    init() unless $bridge_config;

    my $args_json = encode_json($args);
    
    # Escape quotes for shell
    $args_json =~ s/(["\\])/\\$1/g;
    
    my $cmd = "php \"$spp_cli_path\" bridge:call \"$class_name\" \"$method_name\" \"$args_json\" 2>&1";
    my $output = `$cmd`;
    
    if ($? != 0) {
        die "PHP CLI Execution failed: $output";
    }

    my $json_start = index($output, '{');
    if ($json_start == -1) {
        die "Invalid response from PHP bridge: $output";
    }

    my $parsed = decode_json(substr($output, $json_start));
    
    if ($parsed->{success}) {
        return $parsed->{data};
    } else {
        die "PHP Bridge Error: " . $parsed->{error};
    }
}

# Auto-init attempt
eval { init(); };

1;
