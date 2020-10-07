<?php
/**
 * PhpDocBuilder module.
 *
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2020 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto;

use danog\MadelineProto\Async\AsyncConstruct;
use danog\MadelineProto\Db\DbPropertiesTrait;
use danog\MadelineProto\Files\Server;
use danog\MadelineProto\MTProtoTools\Crypt;
use danog\MadelineProto\MTProtoTools\GarbageCollector;
use danog\MadelineProto\MTProtoTools\MinDatabase;
use danog\MadelineProto\MTProtoTools\PasswordCalculator;
use danog\MadelineProto\MTProtoTools\ReferenceDatabase;
use danog\MadelineProto\MTProtoTools\UpdatesState;
use danog\MadelineProto\TL\TL;
use danog\MadelineProto\TL\TLConstructors;
use danog\MadelineProto\TL\TLMethods;
use danog\MadelineProto\TON\ADNLConnection;
use danog\MadelineProto\TON\APIFactory as TAPIFactory;
use danog\MadelineProto\TON\InternalDoc as TInternalDoc;
use danog\MadelineProto\TON\Lite;
use HaydenPierce\ClassFinder\ClassFinder;
use phpDocumentor\Reflection\DocBlock\Tags\Author;
use phpDocumentor\Reflection\DocBlock\Tags\Deprecated;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\See;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionClassConstant;

class PhpDocBuilder
{
    private DocBlockFactory $factory;
    public function __construct()
    {
        $this->factory = DocBlockFactory::createInstance();
    }
    public function run()
    {
        $classes = ClassFinder::getClassesInNamespace('danog\\MadelineProto', ClassFinder::RECURSIVE_MODE);
        foreach ($classes as $class) {
            if (\in_array($class, [
                AnnotationsBuilder::class,
                APIFactory::class,
                APIWrapper::class,
                AbstractAPIFactory::class,
                Bug74586Exception::class,
                Connection::class,
                ContextConnector::class,
                DataCenter::class,
                DataCenterConnection::class,
                DoHConnector::class,
                DocsBuilder::class,
                InternalDoc::class,
                Lang::class,
                LightState::class,
                Magic::class,
                PhpDocBuilder::class,
                RSA::class,
                Serialization::class,
                SessionPaths::class,
                SettingsEmpty::class,
                SettingsAbstract::class,
                Snitch::class,
                AsyncConstruct::class,
                Server::class, // Remove when done
                VoIP::class,

                Crypt::class,
                NothingInTheSocketException::class,

                GarbageCollector::class,
                MinDatabase::class,
                PasswordCalculator::class,
                ReferenceDatabase::class,
                UpdatesState::class,

                TL::class,
                TLConstructors::class,
                TLMethods::class,

                ADNLConnection::class,
                TAPIFactory::class,
                TInternalDoc::class,
                Lite::class,
            ]) || str_starts_with($class, 'danog\\MadelineProto\\Ipc')
            || str_starts_with($class, 'danog\\MadelineProto\\Loop\\Update')
            || str_starts_with($class, 'danog\\MadelineProto\\Loop\\Connection')
            || str_starts_with($class, 'danog\\MadelineProto\\MTProto\\')
            || str_starts_with($class, 'danog\\MadelineProto\\MTProtoSession\\')
            || str_starts_with($class, 'danog\\MadelineProto\\Db\\NullCache')) {
                continue;
            }
            $class = new ReflectionClass($class);
            if ($class->isTrait()) {
                continue;
            }
            $this->generate($class);
        }
        $this->generate(new ReflectionClass(DbPropertiesTrait::class));
    }

    private function generate(ReflectionClass $class): void
    {
        $name = $class->getName();
        $doc = $class->getDocComment();
        if (!$doc) {
            throw new Exception("$name has no PHPDOC!");
        }
        $doc = $this->factory->create($doc);
        $title = $doc->getSummary();
        $description = $doc->getDescription();
        $tags = $doc->getTags();

        $seeAlso = [];
        $properties = [];

        $author = new Author("Daniil Gentili", "daniil@daniil.it");
        foreach ($tags as $tag) {
            if ($tag instanceof Author) {
                $author = $tag;
            }
            if ($tag instanceof Deprecated) {
                return;
            }
            if ($tag instanceof Generic && $tag->getName() === 'internal') {
                return;
            }
            if ($tag instanceof See) {
                $seeAlso[$tag->getReference()->__toString()] = $tag->render();
            }
            if ($tag instanceof Property) {
                $properties[$tag->getVariableName()] = [
                    $tag->getType(),
                    $tag->getDescription()
                ];
            }
            if ($tag instanceof InvalidTag && $tag->getName() === 'property') {
                [$type, $description] = \explode(" $", $tag->render(), 2);
                $description .= ' ';
                [$varName, $description] = \explode(" ", $description, 2);
                $properties[$varName] = [
                    \str_replace('@property ', '', $type),
                    $description ?? ''
                ];
            }
        }

        $constants = [];
        foreach ($class->getConstants() as $key => $value) {
            $refl = new ReflectionClassConstant($name, $key);
            $doc = $this->factory->create($refl->getDocComment() ?? '');
            $constants[$key] = [
                $value,
                $description,
                $doc->getDescription()
            ];
        }
    }
}
