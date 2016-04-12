<?php

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Serializer as DocBlockSerializer;
use phpDocumentor\Reflection\DocBlock\Tag;

/**
 * Class DocBlockGenerator
 *
 * @package IDEAnnotator/Generators
 */
class DocBlockGenerator
{
    /**
     * The current class we are working with
     * @var string
     */
    protected $className = '';

    /**
     * @var ReflectionClass
     */
    protected $reflector;

    /**
     * @var
     */
    protected $tagGenerator;

    /**
     * DocBlockGenerator constructor.
     *
     * @param $className
     */
    public function __construct($className)
    {
        $this->className    = $className;
        $this->reflector    = new ReflectionClass($className);
        $this->tagGenerator = new DocBlockTagGenerator($className);
    }

    /**
     * Not that in case there are multiple doblocks for a class,
     * the last one will be returned
     *
     * If we file old style generated docblocks we remove them
     *
     * @return string
     */
    public function getExistingDocBlock()
    {
        $docBlock = $this->reflector->getDocComment();

        return $this->removeOldStyleDocBlock($docBlock);
    }

    /**
     * @return DocBlock|string
     */
    public function getGeneratedDocBlock()
    {
        return $this->mergeGeneratedTagsIntoDocBlock($this->getExistingDocBlock());
    }

    /**
     * @return DocBlock\Tag[]
     */
    public function getExistingTags()
    {
        $docBlock = new DocBlock($this->getExistingDocBlock());
        return $docBlock->getTags();
    }

    /**
     * @return DocBlock\Tag[]
     */
    public function getGeneratedTags()
    {
        return $this->tagGenerator->getTags();
    }

    /**
     * @return array
     */
    public function getTagsMergedWithExisting()
    {
        /**
         * set array keys so we can match existing with generated tags
         */
        $existing = $this->tagGenerator->getSupportedTagTypes();
        foreach($this->getExistingTags() as $tag) {
            $content = $tag->getContent();
            if($tag->getName() === 'property') {
                $existing['properties'][$content] = new Tag($tag->getName(), $content);
            }elseif($tag->getName() === 'method') {
                $existing['methods'][$content] = new Tag($tag->getName(), $content);
            }elseif($tag->getName() === 'mixin') {
                $existing['mixins'][$content] = new Tag($tag->getName(), $content);
            }else{
                $existing['other'][$content] = new Tag($tag->getName(), $content);
            }
        }

        /**
         * Remove the generated tags that already exist
         */
        $tags = $this->tagGenerator->getTags();
        foreach ($tags as $tagType => $tagList) {
            foreach($tagList as $type => $tag) {
                $content = $tag->getContent();
                if(isset($existing[$tagType][$content])) {
                    unset($tags[$tagType][$content]);
                }
            }
        }

        return $tags;
    }

    /**
     * @param string $existingDocBlock
     * @return string
     */
    protected function mergeGeneratedTagsIntoDocBlock($existingDocBlock)
    {
        $docBlock = new DocBlock($existingDocBlock);

        if (!$docBlock->getText()) {
            $docBlock->setText($this->className);
        }

        foreach($this->getTagsMergedWithExisting() as $tags) {
            foreach($tags as $tag) {
                $docBlock->appendTag($tag);
            }
        }

        $serializer = new DocBlockSerializer();
        $docBlock = $serializer->getDocComment($docBlock);

        return $docBlock;
    }

    /**
     * Removes the unnecessary STARTTAG and ENDTAG
     * If they are left behind somehow
     *
     * @param $docBlock
     *
     * @return mixed
     */
    protected function removeOldStyleDocBlock($docBlock)
    {
        $startTag = DataObjectAnnotator::STARTTAG;
        $endTag = DataObjectAnnotator::ENDTAG;

        /**
         * First remove the complete generated docblock
         */
        $replace = "/\n\/\*\*\n"
            . " \* $startTag\n"
            . "([\s\S]*?)"
            . " \* $endTag\n"
            . " \*\/\n/";
        $docBlock = preg_replace($replace, "\n", $docBlock);

        /**
         * Then remove the start and end tag seperate, if it still exists in case a developer has merged
         * the generated docblocks with existing ones.
         */
        $replacements = array(
            "/ \* $startTag\n/",
            "/ \* $endTag\n/"
        );

        $docBlock = preg_replace($replacements, '', $docBlock);

        /**
         * Then the we have a docblock with or without annotations
         * Those will be handled by phpDocumentor
         */

        return $docBlock;
    }
}
