# Homebrew formula for Rift. This repository is its own tap:
#
#   brew tap lucapipolo/rift https://github.com/LucaPipolo/rift
#   brew trust lucapipolo/rift
#   brew install rift
#
# The release workflow sets the version and the four checksums on every stable
# release, so edit everything else here and leave those to it.
class Rift < Formula
  desc "Run Git worktrees side by side, each with its own containers and HTTPS domain"
  homepage "https://github.com/LucaPipolo/rift"
  version "1.1.0"
  license "MIT"

  # The binaries embed their own PHP runtime, so nothing here depends on
  # Homebrew's php: a PHP major bump can never break an installed Rift.
  on_macos do
    on_arm do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-darwin-arm64"
      sha256 "a8b13e7b52c452b99c57edfc3a7272a328f764cb2e672577001b6b5378046dbb"
    end

    on_intel do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-darwin-x86_64"
      sha256 "e17f78d28d8a6c61a6747914839c043e5b024f4ee0b816326de533b890eaeb3c"
    end
  end

  on_linux do
    on_arm do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-linux-arm64"
      sha256 "8ff336dd3617369145d1d49daaf358594934437b9e6e1e11f8f75779db0c6cfe"
    end

    on_intel do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-linux-x86_64"
      sha256 "cb3222b858bf8cac31cc8f9585b047fe899b3511e35290b0cec908bdcf954836"
    end
  end

  def install
    bin.install Dir["*"].first => "rift"
  end

  def caveats
    <<~EOS
      Rift needs Docker running, and *.test resolving to your machine.

      It serves each checkout over HTTPS with a certificate authority it
      creates itself, so the first `rift up` asks for your password once to
      trust it. One router serves every project, so you are asked once per
      machine, not once per project.
    EOS
  end

  test do
    assert_match "Rift", shell_output("#{bin}/rift --version")
  end
end
